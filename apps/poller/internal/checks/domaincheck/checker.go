package domaincheck

import (
	"bufio"
	"context"
	"errors"
	"fmt"
	"net"
	"net/url"
	"regexp"
	"strings"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

const Type = "domain"

type Checker struct {
	lookup func(ctx context.Context, domain string) (string, error)
}

func New() Checker {
	return Checker{lookup: lookupWHOIS}
}

func (c Checker) Type() string {
	return Type
}

func (c Checker) Check(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error) {
	startedAt := time.Now().UTC()
	cfg, err := parseConfig(job)
	if err != nil {
		result := failedResult(job, startedAt, "domain_invalid_name", err.Error(), false)
		return result, err
	}

	whoisText, err := c.lookup(ctx, cfg.domain)
	if err != nil {
		result := failedResult(job, startedAt, "domain_whois_error", err.Error(), true)
		return result, err
	}

	expiresAt, err := parseWHOISExpiration(cfg.domain, whoisText)
	if err != nil {
		result := failedResult(job, startedAt, checkErrorCode(err), err.Error(), false)
		result.Raw = map[string]any{
			"registered": false,
			"domain":     cfg.domain,
		}
		return result, err
	}

	status := resolveExpiration(expiresAt, cfg.warningDays)
	result := checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    status.status,
		CheckedAt: startedAt,
		Duration:  time.Since(startedAt),
		Raw: map[string]any{
			"registered":            true,
			"domain":                cfg.domain,
			"expires_at":            expiresAt.Format(time.RFC3339),
			"days_until_expiration": int(time.Until(expiresAt).Hours() / 24),
			"registrar":             parseWHOISRegistrar(whoisText),
		},
		Error: status.err,
	}

	return result, errorForCheckError(status.err)
}

func parseWHOISRegistrar(whoisText string) string {
	if value, ok := findWHOISField(whoisText, "Registrar"); ok {
		return value
	}

	return ""
}

type config struct {
	domain      string
	warningDays []int
}

func parseConfig(job jobs.CheckJob) (config, error) {
	settings := job.Settings

	domain := stringSetting(settings, "domain", "")
	if domain == "" {
		domain = job.Target
	}

	domain = normalizeDomain(domain)
	if !isValidDomain(domain) {
		return config{}, fmt.Errorf("invalid domain name: %s", domain)
	}

	return config{
		domain:      domain,
		warningDays: intSliceSetting(settings, "warning_days", []int{30, 14, 7, 3, 1}),
	}, nil
}

type expirationStatus struct {
	status checks.ResultStatus
	err    *checks.CheckError
}

func resolveExpiration(expiresAt time.Time, warningDays []int) expirationStatus {
	daysUntilExpiry := int(time.Until(expiresAt).Hours() / 24)
	if daysUntilExpiry < 0 {
		return expirationStatus{
			status: checks.ResultStatusFailed,
			err: &checks.CheckError{
				Code:      "domain_expired",
				Message:   "domain is expired",
				Temporary: false,
			},
		}
	}

	for _, threshold := range warningDays {
		if daysUntilExpiry <= threshold {
			return expirationStatus{status: checks.ResultStatusWarning}
		}
	}

	return expirationStatus{status: checks.ResultStatusSuccess}
}

func lookupWHOIS(ctx context.Context, domain string) (string, error) {
	server := whoisServerForDomain(domain)
	if server == "" {
		return "", fmt.Errorf("unsupported TLD for domain: %s", domain)
	}

	var dialer net.Dialer
	conn, err := dialer.DialContext(ctx, "tcp", net.JoinHostPort(server, "43"))
	if err != nil {
		return "", err
	}
	defer conn.Close()

	if deadline, ok := ctx.Deadline(); ok {
		_ = conn.SetDeadline(deadline)
	}

	if _, err := fmt.Fprintf(conn, "%s\r\n", domain); err != nil {
		return "", err
	}

	var builder strings.Builder
	scanner := bufio.NewScanner(conn)
	for scanner.Scan() {
		builder.WriteString(scanner.Text())
		builder.WriteByte('\n')
	}
	if err := scanner.Err(); err != nil {
		return "", err
	}

	return builder.String(), nil
}

func whoisServerForDomain(domain string) string {
	lower := strings.ToLower(domain)
	switch {
	case strings.HasSuffix(lower, ".ru"), strings.HasSuffix(lower, ".su"), strings.HasSuffix(lower, ".xn--p1ai"), strings.HasSuffix(lower, ".рф"):
		return "whois.tcinet.ru"
	case strings.HasSuffix(lower, ".com"), strings.HasSuffix(lower, ".net"):
		return "whois.verisign-grs.com"
	case strings.HasSuffix(lower, ".org"):
		return "whois.pir.org"
	default:
		return ""
	}
}

func parseWHOISExpiration(domain string, whoisText string) (time.Time, error) {
	tld := domainTLD(domain)
	fields := expirationFieldsForTLD(tld)
	for _, field := range fields {
		if value, ok := findWHOISField(whoisText, field); ok {
			if parsed, err := parseDate(value); err == nil {
				return parsed, nil
			}
		}
	}

	return time.Time{}, &checks.CheckError{
		Code:      "domain_expiration_not_found",
		Message:   "domain expiration date was not found in WHOIS response",
		Temporary: false,
	}
}

func expirationFieldsForTLD(tld string) []string {
	switch tld {
	case "ru", "su", "xn--p1ai", "рф":
		return []string{"paid-till", "free-date"}
	case "com", "net":
		return []string{"Registry Expiry Date", "Registrar Registration Expiration Date", "Expiration Date"}
	case "org":
		return []string{"Registry Expiry Date", "Registrar Registration Expiration Date", "Expiration Date"}
	default:
		return []string{"Registry Expiry Date", "Expiration Date", "paid-till", "free-date"}
	}
}

func findWHOISField(whoisText string, field string) (string, bool) {
	scanner := bufio.NewScanner(strings.NewReader(whoisText))
	prefix := strings.ToLower(field) + ":"
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if strings.HasPrefix(strings.ToLower(line), prefix) {
			return strings.TrimSpace(line[len(field)+1:]), true
		}
	}

	return "", false
}

func parseDate(value string) (time.Time, error) {
	value = strings.TrimSpace(value)
	if value == "" {
		return time.Time{}, fmt.Errorf("empty date")
	}

	if fields := strings.Fields(value); len(fields) > 0 {
		value = fields[0]
	}

	formats := []string{
		time.RFC3339,
		"2006-01-02T15:04:05Z",
		"2006-01-02",
		"02-Jan-2006",
		"2006.01.02",
		"2006/01/02",
	}
	for _, format := range formats {
		if parsed, err := time.Parse(format, value); err == nil {
			return parsed, nil
		}
	}

	return time.Time{}, fmt.Errorf("unsupported expiration date format: %s", value)
}

func failedResult(job jobs.CheckJob, checkedAt time.Time, code string, message string, temporary bool) checks.CheckResult {
	return checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    checks.ResultStatusFailed,
		CheckedAt: checkedAt,
		Duration:  time.Since(checkedAt),
		Raw:       map[string]any{},
		Error: &checks.CheckError{
			Code:      code,
			Message:   message,
			Temporary: temporary,
		},
	}
}

func errorForCheckError(err *checks.CheckError) error {
	if err == nil {
		return nil
	}

	return err
}

func checkErrorCode(err error) string {
	var checkErr *checks.CheckError
	if errors.As(err, &checkErr) {
		return checkErr.Code
	}

	return "domain_whois_error"
}

func normalizeDomain(value string) string {
	value = strings.TrimSpace(strings.ToLower(value))
	if value == "" {
		return ""
	}

	if strings.Contains(value, "://") {
		parsed, err := url.Parse(value)
		if err == nil {
			value = parsed.Hostname()
		}
	}

	host, _, err := net.SplitHostPort(value)
	if err == nil {
		value = host
	}

	return strings.TrimSuffix(value, ".")
}

var domainPattern = regexp.MustCompile(`^[a-z0-9а-яё.-]+$`)

func isValidDomain(domain string) bool {
	if len(domain) < 3 || len(domain) > 253 || strings.HasPrefix(domain, "-") || strings.Contains(domain, "..") {
		return false
	}

	if !strings.Contains(domain, ".") || !domainPattern.MatchString(domain) {
		return false
	}

	labels := strings.Split(domain, ".")
	for _, label := range labels {
		if label == "" || strings.HasPrefix(label, "-") || strings.HasSuffix(label, "-") {
			return false
		}
	}

	return true
}

func domainTLD(domain string) string {
	parts := strings.Split(strings.ToLower(domain), ".")
	if len(parts) == 0 {
		return ""
	}

	return parts[len(parts)-1]
}

func stringSetting(settings map[string]any, key string, fallback string) string {
	if value, ok := settings[key].(string); ok && value != "" {
		return value
	}

	return fallback
}

func intSliceSetting(settings map[string]any, key string, fallback []int) []int {
	values, ok := settings[key]
	if !ok {
		return fallback
	}

	switch typed := values.(type) {
	case []int:
		return typed
	case []any:
		result := make([]int, 0, len(typed))
		for _, value := range typed {
			switch number := value.(type) {
			case int:
				result = append(result, number)
			case int64:
				result = append(result, int(number))
			case float64:
				result = append(result, int(number))
			}
		}
		if len(result) > 0 {
			return result
		}
	}

	return fallback
}
