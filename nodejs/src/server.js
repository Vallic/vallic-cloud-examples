// A Node.js server reading what the platform hands it.
//
// There is no bootstrap file for Node on Vallic Cloud: an application reads
// the environment directly. The names worth knowing:
//
//   PORT                what to listen on — bind this, not a port of your choosing
//   DATABASE_URL        one connection string, which is what most drivers take
//   REDIS_HOST          the cache, when the stack runs one — with REDIS_PORT
//   VALLIC_PRIVATE_DIR  a directory that outlives every release — where uploads go
//   VALLIC_LOG_DIR      where log files go, if you write any, to be collected
//
// https://docs.vallic.com/framework-node
import { createServer } from 'node:http';
import pg from 'pg';

const pool = new pg.Pool({ connectionString: process.env.DATABASE_URL });

const server = createServer(async (req, res) => {
  // What the deploy waits for (health.path). It asks the database: a check
  // that passes because the process is alive is a green tick that means
  // nothing.
  if (req.url === '/healthz') {
    try {
      await pool.query('SELECT 1');
      res.writeHead(200, { 'Cache-Control': 'no-store' }).end('ok\n');
    } catch {
      res.writeHead(503).end('database unreachable\n');
    }
    return;
  }

  res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end(`Hello from ${process.env.VALLIC_ENVIRONMENT ?? 'a laptop'}\n`);
});

// On every interface, not localhost: the proxy reaches the process across the
// container boundary, and 127.0.0.1 inside a container is reachable by nothing.
server.listen(Number(process.env.PORT ?? 3000), '0.0.0.0');

// Deploys and restarts stop the process; finish what is in flight.
process.on('SIGTERM', () => server.close(() => pool.end()));
