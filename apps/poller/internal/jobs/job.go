package jobs

import "time"

type CheckJob struct {
	ID          string
	EventID     string
	MonitorID   string
	Type        string
	Target      string
	Settings    map[string]any
	Expected    map[string]any
	Timeout     time.Duration
	RequestedAt time.Time
	Source      JobSource
}
