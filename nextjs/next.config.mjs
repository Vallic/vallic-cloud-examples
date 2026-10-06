/**
 * Next.js on Vallic Cloud.
 *
 * Nothing here is required by the platform.
 *
 * Do not put the environment's variables under `env` here: those are inlined
 * when the build runs, and a build has no environment of its own — the same
 * artifact is deployed to staging and production. Read process.env in server
 * code, at request time, instead.
 *
 * https://docs.vallic.com/stack-nodejs
 */
const nextConfig = {
  // The platform's proxy already compresses responses; compressing again in
  // the process only spends CPU that answers requests.
  compress: false,
};

export default nextConfig;
