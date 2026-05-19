package main

import (
	"context"
	"os"
	"os/signal"
	"syscall"

	"montry/apps/poller/internal/app"
	"montry/apps/poller/internal/logger"
)

func main() {
	application, err := app.NewFromEnv()
	if err != nil {
		logger.New("poller").Error("failed to bootstrap poller", "error", err)
		os.Exit(1)
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	if err := application.Run(ctx); err != nil {
		logger.New("poller").Error("poller stopped with error", "error", err)
		os.Exit(1)
	}
}
