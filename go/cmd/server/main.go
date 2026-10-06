// A Go server reading what the platform hands it.
//
// There is no bootstrap file for Go on Vallic Cloud: an application reads the
// environment directly. The names worth knowing:
//
//	PORT                what to listen on — bind this, not a port of your choosing
//	DATABASE_URL        one connection string, which is what most drivers take
//	REDIS_HOST          the cache, when the stack runs one — with REDIS_PORT
//	VALLIC_PRIVATE_DIR  a directory that outlives every release — where uploads go
//	VALLIC_LOG_DIR      where log files go, if you write any, to be collected
//
// https://docs.vallic.com/framework-node
package main

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	_ "github.com/jackc/pgx/v5/stdlib"
)

func main() {
	db, err := sql.Open("pgx", os.Getenv("DATABASE_URL"))
	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()

	mux := http.NewServeMux()

	// What the deploy waits for (health.path). It asks the database: a check
	// that passes because the process is alive is a green tick that means
	// nothing.
	mux.HandleFunc("GET /healthz", func(w http.ResponseWriter, r *http.Request) {
		ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
		defer cancel()
		if err := db.PingContext(ctx); err != nil {
			http.Error(w, "database unreachable", http.StatusServiceUnavailable)
			return
		}
		w.Header().Set("Cache-Control", "no-store")
		fmt.Fprintln(w, "ok")
	})

	mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
		env := os.Getenv("VALLIC_ENVIRONMENT")
		if env == "" {
			env = "a laptop"
		}
		fmt.Fprintf(w, "Hello from %s\n", env)
	})

	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	// On every interface, not localhost: the proxy reaches the process across
	// the container boundary.
	server := &http.Server{Addr: ":" + port, Handler: mux, ReadHeaderTimeout: 10 * time.Second}

	// Deploys and restarts stop the process; finish what is in flight.
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGTERM, os.Interrupt)
	defer stop()
	go func() {
		<-ctx.Done()
		shutdown, cancel := context.WithTimeout(context.Background(), 20*time.Second)
		defer cancel()
		server.Shutdown(shutdown)
	}()

	if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Fatal(err)
	}
}
