// Scrubs people out of a database that arrived from somewhere else.
//
// Built beside the server (vallic.yaml) and called from
// .vallic/commands/sanitization.yml, which the platform runs after a copy or
// a restore into staging or development — never on production.
//
// https://docs.vallic.com/backup-storage#sanitising-what-arrives
package main

import (
	"database/sql"
	"log"
	"os"

	_ "github.com/jackc/pgx/v5/stdlib"
)

func main() {
	if os.Getenv("VALLIC_ENVIRONMENT_TYPE") == "production" {
		log.Fatal("refusing to sanitise production")
	}

	db, err := sql.Open("pgx", os.Getenv("DATABASE_URL"))
	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()

	// Your own tables that hold people; these names are an example. A failing
	// statement exits non-zero, which stops the rest and fails the task.
	for _, statement := range []string{
		`UPDATE users SET email = 'user' || id || '@example.test'`,
		`TRUNCATE password_resets`,
	} {
		if _, err := db.Exec(statement); err != nil {
			log.Fatal(err)
		}
	}

	log.Println("sanitised")
}
