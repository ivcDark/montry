package logger

import (
	"encoding/json"
	"fmt"
	"io"
	"os"
	"strings"
	"sync"
	"time"
)

type Logger struct {
	service string
	output  io.Writer
	mu      sync.Mutex
}

func New(service string) *Logger {
	return NewWithWriter(service, os.Stdout)
}

func NewWithWriter(service string, output io.Writer) *Logger {
	return &Logger{
		service: service,
		output:  output,
	}
}

func (l *Logger) Info(message string, fields ...any) {
	l.print("INFO", message, fields...)
}

func (l *Logger) Error(message string, fields ...any) {
	l.print("ERROR", message, fields...)
}

func (l *Logger) Warn(message string, fields ...any) {
	l.print("WARN", message, fields...)
}

func (l *Logger) print(level string, message string, fields ...any) {
	record := map[string]any{
		"timestamp": time.Now().UTC().Format(time.RFC3339Nano),
		"service":   l.service,
		"component": "poller",
		"level":     strings.ToLower(level),
		"message":   message,
	}

	for key, value := range fieldsToMap(fields...) {
		record[key] = redactValue(key, value)
	}

	encoded, err := json.Marshal(record)
	if err != nil {
		encoded, _ = json.Marshal(map[string]any{
			"timestamp": time.Now().UTC().Format(time.RFC3339Nano),
			"service":   l.service,
			"component": "poller",
			"level":     "error",
			"message":   "failed to encode log record",
		})
	}

	l.mu.Lock()
	defer l.mu.Unlock()

	_, _ = l.output.Write(append(encoded, '\n'))
}

func fieldsToMap(fields ...any) map[string]any {
	result := make(map[string]any, len(fields)/2)

	for i := 0; i < len(fields); i += 2 {
		key, ok := fields[i].(string)
		if !ok || key == "" {
			key = fmt.Sprintf("field_%d", i)
		}

		if i+1 >= len(fields) {
			result[key] = true

			continue
		}

		result[key] = normalizeValue(fields[i+1])
	}

	return result
}

func normalizeValue(value any) any {
	if err, ok := value.(error); ok {
		return err.Error()
	}

	return value
}

func redactValue(key string, value any) any {
	if isSensitiveKey(key) {
		return "[redacted]"
	}

	switch typed := value.(type) {
	case map[string]any:
		redacted := make(map[string]any, len(typed))
		for nestedKey, nestedValue := range typed {
			redacted[nestedKey] = redactValue(nestedKey, nestedValue)
		}

		return redacted
	default:
		return value
	}
}

func isSensitiveKey(key string) bool {
	normalized := strings.ToLower(key)
	sensitiveKeys := []string{
		"api_key",
		"authorization",
		"cookie",
		"password",
		"secret",
		"token",
		"verification_code",
	}

	for _, sensitiveKey := range sensitiveKeys {
		if strings.Contains(normalized, sensitiveKey) {
			return true
		}
	}

	return false
}
