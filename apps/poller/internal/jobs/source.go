package jobs

type JobSource string

const (
	SourceScheduled JobSource = "scheduled"
	SourceManual    JobSource = "manual"
)
