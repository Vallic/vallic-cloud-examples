// Scrubs people out of a database that arrived from somewhere else.
//
// Called from .vallic/commands/sanitization.yml, which the platform runs after
// a copy or a restore into staging or development — never on production.
//
// https://docs.vallic.com/backup-storage#sanitising-what-arrives
import pg from 'pg';

if (process.env.VALLIC_ENVIRONMENT_TYPE === 'production') {
  console.error('Refusing to sanitise production.');
  process.exit(1);
}

const client = new pg.Client({ connectionString: process.env.DATABASE_URL });
await client.connect();

try {
  // Your own tables that hold people; these names are an example. A failing
  // statement exits non-zero, which stops the rest and fails the task.
  await client.query(`UPDATE users SET email = 'user' || id || '@example.test'`);
  await client.query('TRUNCATE password_resets');
} finally {
  await client.end();
}

console.log('Sanitised.');
