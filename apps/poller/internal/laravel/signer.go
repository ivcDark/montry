package laravel

import "net/http"

type Signer interface {
	Sign(req *http.Request)
}

type BearerTokenSigner struct {
	token string
}

func NewBearerTokenSigner(token string) BearerTokenSigner {
	return BearerTokenSigner{token: token}
}

func (s BearerTokenSigner) Sign(req *http.Request) {
	if s.token == "" {
		return
	}

	req.Header.Set("Authorization", "Bearer "+s.token)
}
