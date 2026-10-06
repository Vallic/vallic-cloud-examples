"""The rembg container's entrypoint, mounted from the release at /app.

A handful of your own code on an extra: the image comes from a registry,
this file comes from your repository, and the two deploy together — a
deploy brings the new file and restarts the container.

https://docs.vallic.com/extras#your-own-code-on-an-extra
"""

import os
import tomllib

with open("/app/settings.toml", "rb") as f:
    settings = tomllib.load(f)

# Set in the console and named under extra.voyager.env.required in
# vallic.yaml. The container gets those variables and only those — read here
# so a missing one stops the container at once rather than at first use.
os.environ["REMBG_TOKEN"]

# HTTPS_PROXY and HTTP_PROXY are set because vallic.yaml asks for
# `egress: internet`; the model download goes out through them.
os.execvp(
    "rembg",
    ["rembg", "s", "--host", "0.0.0.0", "--port", str(settings["port"])],
)
