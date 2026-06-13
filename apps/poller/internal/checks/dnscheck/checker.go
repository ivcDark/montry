package dnscheck

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/url"
	"sort"
	"strings"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/checks/checkutil"
	"montry/apps/poller/internal/jobs"
)

const Type = "dns"

type Checker struct{}

func New() Checker {
	return Checker{}
}

func (c Checker) Type() string {
	return Type
}

func (c Checker) Check(ctx context.Context, job jobs.CheckJob) (checks.CheckResult, error) {
	startedAt := time.Now().UTC()
	cfg, err := parseConfig(job)
	if err != nil {
		return failedResult(job, startedAt, "dns_invalid_config", err.Error(), false), err
	}

	resolver := resolverFor(cfg.nameservers)
	responseStartedAt := time.Now()
	records, lookupErr := lookupRecords(ctx, resolver, cfg.domain, cfg.recordTypes)
	responseTime := time.Since(responseStartedAt)

	raw := map[string]any{
		"resolved":         len(records) > 0,
		"domain":           cfg.domain,
		"record_types":     cfg.recordTypes,
		"records":          records,
		"response_time_ms": responseTime.Milliseconds(),
	}

	result := checks.CheckResult{
		EventID:   job.EventID,
		MonitorID: job.MonitorID,
		Type:      Type,
		Status:    checks.ResultStatusSuccess,
		CheckedAt: startedAt,
		Duration:  time.Since(startedAt),
		Raw:       raw,
	}

	if lookupErr != nil {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "dns_lookup_failed",
			Message:   lookupErr.Error(),
			Temporary: isTemporaryDNSError(lookupErr),
		}

		return result, lookupErr
	}

	if cfg.expectedResolves && len(records) < cfg.minRecords {
		result.Status = checks.ResultStatusFailed
		result.Error = &checks.CheckError{
			Code:      "dns_not_enough_records",
			Message:   fmt.Sprintf("expected at least %d DNS records, got %d", cfg.minRecords, len(records)),
			Temporary: true,
		}
	}

	return result, nil
}

type config struct {
	domain           string
	recordTypes      []string
	nameservers      []string
	expectedResolves bool
	minRecords       int
}

func parseConfig(job jobs.CheckJob) (config, error) {
	settings := job.Settings
	expected := job.Expected

	domain := normalizeDomain(checkutil.String(settings, "domain", ""))
	if domain == "" {
		domain = normalizeDomain(job.Target)
	}
	if domain == "" {
		return config{}, fmt.Errorf("domain is required")
	}

	recordTypes := normalizeRecordTypes(checkutil.StringSlice(settings, "record_types", []string{"A", "AAAA"}))
	if len(recordTypes) == 0 {
		return config{}, fmt.Errorf("at least one DNS record type is required")
	}

	minRecords := checkutil.Int(expected, "min_records", 1)
	if minRecords < 0 {
		minRecords = 0
	}

	return config{
		domain:           domain,
		recordTypes:      recordTypes,
		nameservers:      normalizeNameservers(checkutil.StringSlice(settings, "nameservers", []string{})),
		expectedResolves: checkutil.Bool(expected, "resolves", true),
		minRecords:       minRecords,
	}, nil
}

func lookupRecords(ctx context.Context, resolver *net.Resolver, domain string, recordTypes []string) ([]map[string]any, error) {
	records := make([]map[string]any, 0)
	var lastErr error

	for _, recordType := range recordTypes {
		switch recordType {
		case "A", "AAAA":
			ips, err := resolver.LookupIP(ctx, "ip", domain)
			if err != nil {
				lastErr = err
				continue
			}

			for _, ip := range ips {
				if recordType == "A" && ip.To4() == nil {
					continue
				}
				if recordType == "AAAA" && ip.To4() != nil {
					continue
				}

				records = append(records, map[string]any{
					"type":  recordType,
					"value": ip.String(),
				})
			}
		case "CNAME":
			cname, err := resolver.LookupCNAME(ctx, domain)
			if err != nil {
				lastErr = err
				continue
			}
			records = append(records, map[string]any{"type": "CNAME", "value": strings.TrimSuffix(cname, ".")})
		case "MX":
			mxRecords, err := resolver.LookupMX(ctx, domain)
			if err != nil {
				lastErr = err
				continue
			}
			for _, mx := range mxRecords {
				records = append(records, map[string]any{"type": "MX", "value": strings.TrimSuffix(mx.Host, "."), "priority": int(mx.Pref)})
			}
		case "NS":
			nsRecords, err := resolver.LookupNS(ctx, domain)
			if err != nil {
				lastErr = err
				continue
			}
			for _, ns := range nsRecords {
				records = append(records, map[string]any{"type": "NS", "value": strings.TrimSuffix(ns.Host, ".")})
			}
		case "TXT":
			txtRecords, err := resolver.LookupTXT(ctx, domain)
			if err != nil {
				lastErr = err
				continue
			}
			for _, txt := range txtRecords {
				records = append(records, map[string]any{"type": "TXT", "value": txt})
			}
		}
	}

	if len(records) == 0 && lastErr != nil {
		return records, lastErr
	}

	return records, nil
}

func resolverFor(nameservers []string) *net.Resolver {
	if len(nameservers) == 0 {
		return net.DefaultResolver
	}

	index := 0
	return &net.Resolver{
		PreferGo: true,
		Dial: func(ctx context.Context, network string, address string) (net.Conn, error) {
			nameserver := nameservers[index%len(nameservers)]
			index++

			dialer := net.Dialer{Timeout: 5 * time.Second}
			return dialer.DialContext(ctx, network, net.JoinHostPort(nameserver, "53"))
		},
	}
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

	if host, _, err := net.SplitHostPort(value); err == nil {
		value = host
	}

	value = strings.Trim(value, "/")

	return strings.TrimSuffix(value, ".")
}

func normalizeRecordTypes(values []string) []string {
	allowed := map[string]bool{"A": true, "AAAA": true, "CNAME": true, "MX": true, "NS": true, "TXT": true}
	seen := make(map[string]bool, len(values))
	result := make([]string, 0, len(values))

	for _, value := range values {
		recordType := strings.ToUpper(strings.TrimSpace(value))
		if allowed[recordType] && !seen[recordType] {
			seen[recordType] = true
			result = append(result, recordType)
		}
	}

	sort.Strings(result)

	return result
}

func normalizeNameservers(values []string) []string {
	result := make([]string, 0, len(values))
	for _, value := range values {
		value = strings.TrimSpace(value)
		if value == "" {
			continue
		}
		if host, _, err := net.SplitHostPort(value); err == nil {
			value = host
		}
		result = append(result, value)
	}

	return result
}

func isTemporaryDNSError(err error) bool {
	if err == nil {
		return false
	}

	var dnsErr *net.DNSError
	if errors.As(err, &dnsErr) {
		return dnsErr.IsTimeout || dnsErr.IsTemporary
	}

	return strings.Contains(strings.ToLower(err.Error()), "timeout")
}
