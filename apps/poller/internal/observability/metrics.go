package observability

import (
	"fmt"
	"sort"
	"strings"
	"sync"
)

var checkDurationBuckets = []float64{0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30, 60}

type Metrics struct {
	mu sync.RWMutex

	counters   map[string]float64
	gauges     map[string]float64
	histograms map[string]*histogramSeries
}

type histogramSeries struct {
	name    string
	labels  labels
	buckets []float64
	counts  []uint64
	count   uint64
	sum     float64
}

type labels map[string]string

func NewMetrics() *Metrics {
	return &Metrics{
		counters:   map[string]float64{},
		gauges:     map[string]float64{},
		histograms: map[string]*histogramSeries{},
	}
}

func (m *Metrics) IncJobs(checkType string, source string, status string) {
	m.inc("montry_poller_jobs_total", labels{
		"check_type": normalizeLabelValue(checkType, "unknown"),
		"source":     normalizeLabelValue(source, "unknown"),
		"status":     normalizeLabelValue(status, "unknown"),
	})
}

func (m *Metrics) ObserveCheckDuration(checkType string, status string, seconds float64) {
	m.observe("montry_poller_check_duration_seconds", labels{
		"check_type": normalizeLabelValue(checkType, "unknown"),
		"status":     normalizeLabelValue(status, "unknown"),
	}, checkDurationBuckets, seconds)
}

func (m *Metrics) IncResultDelivery(checkType string, status string) {
	m.inc("montry_poller_result_delivery_total", labels{
		"check_type": normalizeLabelValue(checkType, "unknown"),
		"status":     normalizeLabelValue(status, "unknown"),
	})
}

func (m *Metrics) SetQueueStats(used int, capacity int) {
	m.setGauge("montry_poller_queue_buffer_used", nil, float64(used))
	m.setGauge("montry_poller_queue_buffer_capacity", nil, float64(capacity))
}

func (m *Metrics) SetWorkerCount(workers int) {
	m.setGauge("montry_poller_workers", nil, float64(workers))
}

func (m *Metrics) Render() string {
	m.mu.RLock()
	defer m.mu.RUnlock()

	var builder strings.Builder

	builder.WriteString("# HELP montry_poller_build_info Static poller build information.\n")
	builder.WriteString("# TYPE montry_poller_build_info gauge\n")
	builder.WriteString(`montry_poller_build_info{service="poller"} 1`)
	builder.WriteString("\n\n")

	m.renderCounters(&builder)
	m.renderGauges(&builder)
	m.renderHistograms(&builder)

	return builder.String()
}

func (m *Metrics) inc(name string, metricLabels labels) {
	m.mu.Lock()
	defer m.mu.Unlock()

	m.counters[seriesKey(name, metricLabels)]++
}

func (m *Metrics) setGauge(name string, metricLabels labels, value float64) {
	m.mu.Lock()
	defer m.mu.Unlock()

	m.gauges[seriesKey(name, metricLabels)] = value
}

func (m *Metrics) observe(name string, metricLabels labels, buckets []float64, value float64) {
	if value < 0 {
		value = 0
	}

	m.mu.Lock()
	defer m.mu.Unlock()

	key := seriesKey(name, metricLabels)
	series, ok := m.histograms[key]
	if !ok {
		series = &histogramSeries{
			name:    name,
			labels:  cloneLabels(metricLabels),
			buckets: append([]float64(nil), buckets...),
			counts:  make([]uint64, len(buckets)),
		}
		m.histograms[key] = series
	}

	for index, bucket := range series.buckets {
		if value <= bucket {
			series.counts[index]++
		}
	}

	series.count++
	series.sum += value
}

func (m *Metrics) renderCounters(builder *strings.Builder) {
	groups := groupedSeries(m.counters)

	for _, name := range sortedKeys(groups) {
		builder.WriteString("# HELP ")
		builder.WriteString(name)
		builder.WriteString(" Poller counter metric.\n")
		builder.WriteString("# TYPE ")
		builder.WriteString(name)
		builder.WriteString(" counter\n")

		for _, sample := range groups[name] {
			builder.WriteString(sample.line)
			builder.WriteByte('\n')
		}

		builder.WriteByte('\n')
	}
}

func (m *Metrics) renderGauges(builder *strings.Builder) {
	groups := groupedSeries(m.gauges)

	for _, name := range sortedKeys(groups) {
		builder.WriteString("# HELP ")
		builder.WriteString(name)
		builder.WriteString(" Poller gauge metric.\n")
		builder.WriteString("# TYPE ")
		builder.WriteString(name)
		builder.WriteString(" gauge\n")

		for _, sample := range groups[name] {
			builder.WriteString(sample.line)
			builder.WriteByte('\n')
		}

		builder.WriteByte('\n')
	}
}

func (m *Metrics) renderHistograms(builder *strings.Builder) {
	keys := sortedKeys(m.histograms)
	seen := map[string]bool{}

	for _, key := range keys {
		series := m.histograms[key]
		if !seen[series.name] {
			seen[series.name] = true
			builder.WriteString("# HELP ")
			builder.WriteString(series.name)
			builder.WriteString(" Poller duration histogram.\n")
			builder.WriteString("# TYPE ")
			builder.WriteString(series.name)
			builder.WriteString(" histogram\n")
		}

		for index, bucket := range series.buckets {
			builder.WriteString(formatSample(series.name+"_bucket", series.labels.with("le", formatFloat(bucket)), float64(series.counts[index])))
			builder.WriteByte('\n')
		}

		builder.WriteString(formatSample(series.name+"_bucket", series.labels.with("le", "+Inf"), float64(series.count)))
		builder.WriteByte('\n')
		builder.WriteString(formatSample(series.name+"_sum", series.labels, series.sum))
		builder.WriteByte('\n')
		builder.WriteString(formatSample(series.name+"_count", series.labels, float64(series.count)))
		builder.WriteByte('\n')
	}

	if len(keys) > 0 {
		builder.WriteByte('\n')
	}
}

type renderedSample struct {
	name string
	line string
}

func groupedSeries(values map[string]float64) map[string][]renderedSample {
	groups := map[string][]renderedSample{}

	for key, value := range values {
		name, metricLabels := splitSeriesKey(key)
		groups[name] = append(groups[name], renderedSample{
			name: name,
			line: formatSample(name, metricLabels, value),
		})
	}

	for name := range groups {
		sort.Slice(groups[name], func(i, j int) bool {
			return groups[name][i].line < groups[name][j].line
		})
	}

	return groups
}

func seriesKey(name string, metricLabels labels) string {
	if len(metricLabels) == 0 {
		return name
	}

	keys := sortedKeys(metricLabels)
	parts := make([]string, 0, len(keys)+1)
	parts = append(parts, name)

	for _, key := range keys {
		parts = append(parts, key+"="+metricLabels[key])
	}

	return strings.Join(parts, "|")
}

func splitSeriesKey(key string) (string, labels) {
	parts := strings.Split(key, "|")
	metricLabels := labels{}

	for _, part := range parts[1:] {
		name, value, ok := strings.Cut(part, "=")
		if ok {
			metricLabels[name] = value
		}
	}

	return parts[0], metricLabels
}

func formatSample(name string, metricLabels labels, value float64) string {
	if len(metricLabels) == 0 {
		return fmt.Sprintf("%s %s", name, formatFloat(value))
	}

	keys := sortedKeys(metricLabels)
	pairs := make([]string, 0, len(keys))
	for _, key := range keys {
		pairs = append(pairs, fmt.Sprintf(`%s="%s"`, key, escapeLabelValue(metricLabels[key])))
	}

	return fmt.Sprintf("%s{%s} %s", name, strings.Join(pairs, ","), formatFloat(value))
}

func (l labels) with(key string, value string) labels {
	next := cloneLabels(l)
	next[key] = value

	return next
}

func cloneLabels(metricLabels labels) labels {
	next := labels{}

	for key, value := range metricLabels {
		next[key] = value
	}

	return next
}

func sortedKeys[V any](values map[string]V) []string {
	keys := make([]string, 0, len(values))
	for key := range values {
		keys = append(keys, key)
	}

	sort.Strings(keys)

	return keys
}

func normalizeLabelValue(value string, fallback string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return fallback
	}

	return value
}

func escapeLabelValue(value string) string {
	value = strings.ReplaceAll(value, `\`, `\\`)
	value = strings.ReplaceAll(value, "\n", `\n`)
	value = strings.ReplaceAll(value, `"`, `\"`)

	return value
}

func formatFloat(value float64) string {
	return strings.TrimRight(strings.TrimRight(fmt.Sprintf("%.6f", value), "0"), ".")
}
