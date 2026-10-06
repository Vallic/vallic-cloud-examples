// An Express application reading what the platform hands it.
//
// https://docs.vallic.com/framework-node
// https://docs.vallic.com/variables
import express from 'express';
import session from 'express-session';
import { RedisStore } from 'connect-redis';
import { createClient } from 'redis';
import pg from 'pg';

const app = express();

// The platform's proxy terminates TLS and is the one hop in front of the
// process. Trusting it is what makes req.protocol 'https', req.ip the visitor,
// and a `secure` cookie actually get set.
app.set('trust proxy', 1);

// One connection string; pg takes it as it is.
const pool = new pg.Pool({ connectionString: process.env.DATABASE_URL });

// Sessions in Valkey, not in the process's memory: with more than one web
// server the next request can land on another machine.
const redis = createClient({
  socket: { host: process.env.REDIS_HOST ?? '127.0.0.1', port: Number(process.env.REDIS_PORT ?? 6379) },
});
await redis.connect();

app.use(session({
  store: new RedisStore({ client: redis, prefix: `${process.env.VALLIC_SLUG ?? 'local'}:sess:` }),
  secret: process.env.SESSION_SECRET ?? 'not-a-secret-on-a-laptop',
  resave: false,
  saveUninitialized: false,
  cookie: { secure: process.env.VALLIC_ENVIRONMENT !== undefined, httpOnly: true, sameSite: 'lax' },
}));

// What the deploy waits for (health.path). It asks the database rather than
// answering because the process is alive.
app.get('/healthz', async (req, res) => {
  try {
    await pool.query('SELECT 1');
    res.set('Cache-Control', 'no-store').send('ok\n');
  } catch {
    res.status(503).send('database unreachable\n');
  }
});

app.get('/', (req, res) => {
  req.session.visits = (req.session.visits ?? 0) + 1;
  res.send(`Visit ${req.session.visits} on ${process.env.VALLIC_ENVIRONMENT ?? 'a laptop'}\n`);
});

// On every interface, not localhost: the proxy reaches the process across the
// container boundary.
const server = app.listen(Number(process.env.PORT ?? 3000), '0.0.0.0');

// Deploys and restarts stop the process; finish what is in flight.
process.on('SIGTERM', () => server.close(async () => {
  await pool.end();
  await redis.quit();
}));
