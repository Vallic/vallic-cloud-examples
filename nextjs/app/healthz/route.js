// What the deploy waits for (health.path in vallic.yaml).
//
// Asks the database rather than answering because the process is alive: a
// check that passes while the site cannot reach its data means nothing.
// Anything from 200 to 399 is healthy.
import pg from 'pg';

// Read at request time, never prerendered at build — a build has no database.
export const dynamic = 'force-dynamic';

const pool = new pg.Pool({ connectionString: process.env.DATABASE_URL });

export async function GET() {
  try {
    await pool.query('SELECT 1');
    return new Response('ok\n', { headers: { 'Cache-Control': 'no-store' } });
  } catch {
    return new Response('database unreachable\n', { status: 503 });
  }
}
