package scheduler

import (
	"context"

	"montri/apps/poller/internal/jobs"
	"montri/apps/poller/internal/laravel"
)

type DueFetcher struct {
	client laravel.LaravelClient
	limit  int
}

func NewDueFetcher(client laravel.LaravelClient, limit int) DueFetcher {
	return DueFetcher{client: client, limit: limit}
}

func (f DueFetcher) Fetch(ctx context.Context) ([]jobs.CheckJob, error) {
	return f.client.FetchDueChecks(ctx, f.limit)
}
