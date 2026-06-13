package checks

type CheckError struct {
	Code      string
	Message   string
	Temporary bool
}

func (e *CheckError) Error() string {
	if e == nil {
		return ""
	}

	return e.Message
}
