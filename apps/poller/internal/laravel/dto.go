package laravel

import (
	"strconv"
	"time"

	"montry/apps/poller/internal/checks"
	"montry/apps/poller/internal/jobs"
)

type dueChecksResponse struct {
	Data []checkJobPayload `json:"data"`
}

type checkJobPayload struct {
	ID          string         `json:"id"`
	EventID     string         `json:"event_id"`
	MonitorID   any            `json:"monitor_id"`
	CheckType   string         `json:"check_type"`
	Target      string         `json:"target"`
	Settings    map[string]any `json:"settings"`
	Expected    map[string]any `json:"expected"`
	TimeoutMS   int64          `json:"timeout_ms"`
	RequestedAt string         `json:"requested_at"`
}

func (p checkJobPayload) toJob(source jobs.JobSource) (jobs.CheckJob, error) {
	requestedAt, err := time.Parse(time.RFC3339, p.RequestedAt)
	if err != nil {
		return jobs.CheckJob{}, err
	}

	timeout := time.Duration(p.TimeoutMS) * time.Millisecond

	return jobs.CheckJob{
		ID:          p.ID,
		EventID:     p.EventID,
		MonitorID:   stringifyID(p.MonitorID),
		Type:        p.CheckType,
		Target:      p.Target,
		Settings:    nonNilMap(p.Settings),
		Expected:    nonNilMap(p.Expected),
		Timeout:     timeout,
		RequestedAt: requestedAt,
		Source:      source,
	}, nil
}

type checkResultPayload struct {
	EventID    string              `json:"event_id"`
	MonitorID  string              `json:"monitor_id"`
	CheckType  string              `json:"check_type"`
	Status     checks.ResultStatus `json:"status"`
	CheckedAt  string              `json:"checked_at"`
	DurationMS int64               `json:"duration_ms"`
	Result     map[string]any      `json:"result"`
	Error      *checks.CheckError  `json:"error"`
}

func newCheckResultPayload(result checks.CheckResult) checkResultPayload {
	return checkResultPayload{
		EventID:    result.EventID,
		MonitorID:  result.MonitorID,
		CheckType:  result.Type,
		Status:     result.Status,
		CheckedAt:  result.CheckedAt.Format(time.RFC3339),
		DurationMS: result.Duration.Milliseconds(),
		Result:     nonNilMap(result.Raw),
		Error:      result.Error,
	}
}

func stringifyID(value any) string {
	switch typed := value.(type) {
	case string:
		return typed
	case float64:
		return strconv.FormatInt(int64(typed), 10)
	case int:
		return strconv.Itoa(typed)
	case int64:
		return strconv.FormatInt(typed, 10)
	default:
		return ""
	}
}

func nonNilMap(value map[string]any) map[string]any {
	if value == nil {
		return map[string]any{}
	}

	return value
}
