package checks

import "time"

type ResultStatus string

const (
	ResultStatusSuccess ResultStatus = "success"
	ResultStatusWarning ResultStatus = "warning"
	ResultStatusFailed  ResultStatus = "failed"
)

type CheckResult struct {
	EventID       string
	MonitorID     string
	Type          string
	Status        ResultStatus
	CheckedAt     time.Time
	Duration      time.Duration
	Raw           map[string]any
	Error         *CheckError
	CorrelationID string
	TraceParent   string
}
