package checks

import (
	"fmt"
	"sort"
)

type Registry interface {
	Register(checker Checker) error
	Get(checkType string) (Checker, error)
	All() []Checker
}

type CheckerRegistry struct {
	checkers map[string]Checker
}

func NewRegistry() *CheckerRegistry {
	return &CheckerRegistry{
		checkers: make(map[string]Checker),
	}
}

func (r *CheckerRegistry) Register(checker Checker) error {
	if checker == nil {
		return fmt.Errorf("checker is nil")
	}

	checkType := checker.Type()
	if checkType == "" {
		return fmt.Errorf("checker type is empty")
	}

	if _, exists := r.checkers[checkType]; exists {
		return fmt.Errorf("checker type %q is already registered", checkType)
	}

	r.checkers[checkType] = checker

	return nil
}

func (r *CheckerRegistry) Get(checkType string) (Checker, error) {
	checker, exists := r.checkers[checkType]
	if !exists {
		return nil, fmt.Errorf("unknown check type: %s", checkType)
	}

	return checker, nil
}

func (r *CheckerRegistry) All() []Checker {
	types := make([]string, 0, len(r.checkers))
	for checkType := range r.checkers {
		types = append(types, checkType)
	}

	sort.Strings(types)

	checkers := make([]Checker, 0, len(types))
	for _, checkType := range types {
		checkers = append(checkers, r.checkers[checkType])
	}

	return checkers
}
