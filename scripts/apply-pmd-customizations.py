"""Restore the reviewed BC delta on the pinned PMD revision, without overwriting conflicts."""

import argparse
import hashlib
import json
from pathlib import Path
import shutil
import subprocess


def apply(package: Path, bundle: Path) -> None:
    manifest = json.loads((bundle / "manifest.json").read_text(encoding="utf-8"))
    revision = subprocess.check_output(["git", "-C", str(package), "rev-parse", "HEAD"], text=True).strip()
    if revision != manifest["revision"]:
        raise RuntimeError("Revisão PMD incompatível com o delta BC.")
    patch = bundle / "bc-customizations.patch"
    if hashlib.sha256(patch.read_bytes()).hexdigest() != manifest["patch_sha256"]:
        raise RuntimeError("Checksum do patch BC inválido.")
    copies = []
    for name, expected in manifest["added"].items():
        relative = Path(name)
        if relative.is_absolute() or ".." in relative.parts:
            raise RuntimeError("Caminho inválido no manifesto BC.")
        source, destination = bundle / "added" / relative, package / relative
        if hashlib.sha256(source.read_bytes()).hexdigest() != expected:
            raise RuntimeError(f"Checksum inválido: {name}")
        if destination.exists() and hashlib.sha256(destination.read_bytes()).hexdigest() != expected:
            raise RuntimeError(f"Alteração local conflitante: {name}")
        copies.append((source, destination))
    command = ["git", "-C", str(package), "apply", "--ignore-space-change"]
    clean = subprocess.run(command + ["--check", str(patch)], capture_output=True).returncode == 0
    already_applied = subprocess.run(command + ["--reverse", "--check", str(patch)], capture_output=True).returncode == 0
    if not clean and not already_applied:
        raise RuntimeError("Delta PMD parcial ou conflitante. Revise as alterações locais antes de instalar.")
    if clean:
        subprocess.run(command + [str(patch)], check=True)
    for source, destination in copies:
        destination.parent.mkdir(parents=True, exist_ok=True)
        if not destination.exists():
            shutil.copyfile(source, destination)
    print("Delta BC/PMD conferido e aplicado.")


if __name__ == "__main__":
    root = Path(__file__).resolve().parent.parent
    parser = argparse.ArgumentParser()
    parser.add_argument("--package", type=Path, default=root / "packages/portabilis/pre-matricula-digital")
    options = parser.parse_args()
    apply(options.package.resolve(), root / "patches/pmd")
