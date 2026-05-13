package logger

import (
	"log"
	"os"
)

type Logger struct {
	service string
	logger  *log.Logger
}

func New(service string) *Logger {
	return &Logger{
		service: service,
		logger:  log.New(os.Stdout, "", log.LstdFlags|log.LUTC),
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
	args := []any{"level", level, "service", l.service, "message", message}
	args = append(args, fields...)

	l.logger.Println(args...)
}
