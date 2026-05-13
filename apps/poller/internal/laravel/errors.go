package laravel

import (
	"errors"
	"fmt"
)

type APIError struct {
	StatusCode int
	Body       string
}

func (e *APIError) Error() string {
	return fmt.Sprintf("laravel internal API returned %d: %s", e.StatusCode, e.Body)
}

func (e *APIError) Temporary() bool {
	return e.StatusCode == 429 || e.StatusCode >= 500
}

func AsAPIError(err error, target **APIError) bool {
	return errors.As(err, target)
}
