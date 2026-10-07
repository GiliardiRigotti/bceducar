"""Refresh the reviewed PMD patch bundle without including private runtime files."""

import hashlib
import json
from pathlib import Path
import shutil
import subprocess


def digest(data: bytes) -> str:
    """Checksum independent of CRLF/LF conversion by Git (core.autocrlf) on Windows."""
    if b"\0" not in data:
        data = data.replace(b"\r\n", b"\n")
    return hashlib.sha256(data).hexdigest()

root = Path(__file__).resolve().parent.parent
package = root / "packages/portabilis/pre-matricula-digital"
bundle = root / "patches/pmd"
revision = subprocess.check_output(["git", "-C", str(package), "rev-parse", "HEAD"], text=True).strip()
previous = json.loads((bundle / "manifest.json").read_text(encoding="utf-8"))
if revision != previous["revision"]:
    raise RuntimeError("Revise a compatibilidade da nova revisão antes de atualizar o delta.")
extras = subprocess.check_output(["git", "-C", str(package), "ls-files", "--others", "--exclude-standard", "-z"]).decode().split("\0")
extras = [name for name in extras if name]
for name in extras:
    if ".." in Path(name).parts or not (name.startswith(("resources/ts/", "tests/geo/", "leaflet-dist/")) or name == "vitest.geo.config.ts"):
        raise RuntimeError(f"Arquivo adicional fora do escopo revisado: {name}")
    if (package / name).is_symlink():
        raise RuntimeError(f"Link simbólico fora do escopo do exportador: {name}")
patch = subprocess.check_output(["git", "-C", str(package), "diff", "--binary", "--no-ext-diff", "--no-color", "HEAD", "--", "."])
files = {}
for name in extras:
    destination = bundle / "added" / name
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(package / name, destination)
    files[name] = digest(destination.read_bytes())
(bundle / "bc-customizations.patch").write_bytes(patch)
(bundle / "manifest.json").write_text(json.dumps({"revision": revision,
    "patch_sha256": digest(patch), "added": files}, indent=2) + "\n", encoding="utf-8")
print("Delta BC/PMD atualizado. Revise e versione o conjunto antes de distribuir.")
