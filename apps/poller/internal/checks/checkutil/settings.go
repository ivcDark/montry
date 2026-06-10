package checkutil

import (
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"
)

func String(settings map[string]any, key string, fallback string) string {
	if value, ok := settings[key].(string); ok && strings.TrimSpace(value) != "" {
		return value
	}

	return fallback
}

func Bool(settings map[string]any, key string, fallback bool) bool {
	if value, ok := settings[key].(bool); ok {
		return value
	}

	return fallback
}

func Int(settings map[string]any, key string, fallback int) int {
	value, ok := settings[key]
	if !ok {
		return fallback
	}

	switch typed := value.(type) {
	case int:
		return typed
	case int64:
		return int(typed)
	case float64:
		return int(typed)
	case fmt.Stringer:
		if parsed, err := strconv.Atoi(typed.String()); err == nil {
			return parsed
		}
	}

	return fallback
}

func IntSlice(settings map[string]any, key string, fallback []int) []int {
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
			case fmt.Stringer:
				if parsed, err := strconv.Atoi(number.String()); err == nil {
					result = append(result, parsed)
				}
			}
		}
		if len(result) > 0 {
			return result
		}
	}

	return fallback
}

func StringSlice(settings map[string]any, key string, fallback []string) []string {
	values, ok := settings[key]
	if !ok {
		return fallback
	}

	switch typed := values.(type) {
	case []string:
		return cleanedStrings(typed)
	case []any:
		result := make([]string, 0, len(typed))
		for _, value := range typed {
			if stringValue, ok := value.(string); ok && strings.TrimSpace(stringValue) != "" {
				result = append(result, strings.TrimSpace(stringValue))
			}
		}
		if len(result) > 0 {
			return result
		}
	}

	return fallback
}

func Headers(settings map[string]any, key string) map[string]string {
	values, ok := settings[key].(map[string]any)
	if !ok {
		return map[string]string{}
	}

	headers := make(map[string]string, len(values))
	for header, value := range values {
		header = strings.TrimSpace(header)
		if header == "" {
			continue
		}

		headers[header] = strings.TrimSpace(toString(value))
	}

	return headers
}

func DurationMillis(settings map[string]any, key string) time.Duration {
	value := Int(settings, key, 0)
	if value < 0 {
		return 0
	}

	return time.Duration(value) * time.Millisecond
}

func StatusAllowed(statusCode int, expected []int) bool {
	for _, allowed := range expected {
		if statusCode == allowed {
			return true
		}
	}

	return false
}

func BasicHeaders(headers http.Header) map[string]any {
	result := make(map[string]any)
	for _, key := range []string{"content-type", "server", "location"} {
		if value := headers.Get(key); value != "" {
			result[key] = value
		}
	}

	return result
}

func cleanedStrings(values []string) []string {
	result := make([]string, 0, len(values))
	for _, value := range values {
		value = strings.TrimSpace(value)
		if value != "" {
			result = append(result, value)
		}
	}

	return result
}

func toString(value any) string {
	switch typed := value.(type) {
	case string:
		return typed
	case fmt.Stringer:
		return typed.String()
	default:
		return fmt.Sprint(value)
	}
}
