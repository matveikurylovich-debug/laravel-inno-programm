package problem

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestWrite(t *testing.T) {
	recorder := httptest.NewRecorder()
	Write(recorder, http.StatusUnauthorized, "Требуется авторизация.")

	if recorder.Code != http.StatusUnauthorized {
		t.Fatalf("status %d", recorder.Code)
	}
	if got := recorder.Header().Get("Content-Type"); got != "application/problem+json" {
		t.Fatalf("content type %s", got)
	}

	var body map[string]any
	if err := json.Unmarshal(recorder.Body.Bytes(), &body); err != nil {
		t.Fatal(err)
	}
	if body["title"] != "Unauthorized" || body["detail"] != "Требуется авторизация." || body["status"] != float64(http.StatusUnauthorized) {
		t.Fatalf("body %#v", body)
	}
}
