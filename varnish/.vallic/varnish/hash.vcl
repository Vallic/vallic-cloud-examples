# Added to vcl_hash: what else the cache key varies on, with hash_data().
#
# The platform adds the URL and the host after this, so no `return` here — it
# would leave them out and serve every page from one cached object. A file
# that returns is refused and the policy runs without it.
#
# https://docs.vallic.com/stack-vinyl#rules-of-your-own
sub vcl_hash {
    # One copy per currency, as recv.vcl read it from the cookie. Without a
    # currency the key is what it always was.
    if (req.http.X-Currency) {
        hash_data(req.http.X-Currency);
    }
}
