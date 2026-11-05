#!/usr/bin/env python3
"""Build and verify this standalone plugin's deterministic release ZIP."""

from __future__ import annotations

import argparse
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import stat
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = ROOT.name
FIXED_TIME = (1980, 1, 1, 0, 0, 0)
DENIED_PARTS = {".git", ".github", "node_modules", "tests", "test", "dist", "__pycache__", "tools"}
DENIED_FILES = {".env", "release-files.txt", "composer.json", "composer.lock", "package.json", "package-lock.json"}


def header(text: str, key: str) -> str | None:
    match = re.search(rf"^[ \t]*(?:\*[ \t]*)?{re.escape(key)}:[ \t]*(.+)$", text, re.I | re.M)
    return match.group(1).strip() if match else None


def release_files() -> tuple[list[Path], str]:
    if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", SLUG):
        raise ValueError("Invalid plugin directory name")
    manifest = ROOT / "release-files.txt"
    if not manifest.is_file() or manifest.is_symlink():
        raise ValueError("Missing or linked release manifest")
    selected: set[Path] = set()
    for number, raw in enumerate(manifest.read_text(encoding="utf-8").splitlines(), 1):
        entry = raw.strip()
        if not entry or entry.startswith("#"):
            continue
        if any(char in entry for char in "\\*?[]"):
            raise ValueError(f"Manifest line {number}: globs and backslashes are forbidden")
        relative = PurePosixPath(entry)
        if relative.is_absolute() or any(part in {"", ".", ".."} for part in entry.split("/")):
            raise ValueError(f"Manifest line {number}: unsafe path")
        path = ROOT.joinpath(*relative.parts)
        if not path.exists() and not path.is_symlink():
            raise ValueError(f"Manifest line {number}: missing path {entry}")
        paths = [path] if path.is_file() or path.is_symlink() else sorted(path.rglob("*"))
        for candidate in paths:
            rel = candidate.relative_to(ROOT)
            if any(part in DENIED_PARTS for part in rel.parts) or candidate.name in DENIED_FILES or candidate.name.startswith(".env."):
                raise ValueError(f"Development or secret file in release: {rel}")
            if candidate.is_symlink() or any(parent.is_symlink() for parent in candidate.parents if parent != ROOT):
                raise ValueError(f"Symlink in release: {rel}")
            if candidate.is_dir():
                continue
            if not candidate.is_file() or not stat.S_ISREG(candidate.stat().st_mode):
                raise ValueError(f"Nonregular release file: {rel}")
            selected.add(rel)
    required = {Path(f"{SLUG}.php"), Path("readme.txt"), Path("LICENSE")}
    if not required.issubset(selected):
        raise ValueError("Manifest lacks main plugin file, readme.txt or LICENSE")
    if len(selected) != len({path.as_posix().casefold() for path in selected}):
        raise ValueError("Release files collide on case-insensitive filesystems")
    main = (ROOT / f"{SLUG}.php").read_text(encoding="utf-8")[:8192]
    version = header(main, "Version")
    if not header(main, "Plugin Name") or not version or not re.fullmatch(r"[0-9]+(?:\.[0-9]+){1,3}(?:[-.][A-Za-z0-9]+)*", version):
        raise ValueError("Missing plugin name or valid version header")
    if header(main, "Text Domain") != SLUG or header(main, "License") != "GPL-2.0-or-later":
        raise ValueError("Text Domain or License header mismatch")
    if header((ROOT / "readme.txt").read_text(encoding="utf-8"), "Stable tag") != version:
        raise ValueError("readme.txt Stable tag differs from plugin version")
    if not (ROOT / "LICENSE").read_text(encoding="utf-8").strip():
        raise ValueError("LICENSE is empty")
    return sorted(selected, key=lambda path: path.as_posix()), version


def archive_path(version: str) -> Path:
    return ROOT / "dist" / f"{SLUG}-{version}.zip"


def verify(archive: Path, files: list[Path]) -> str:
    expected = [f"{SLUG}/{path.as_posix()}" for path in files]
    with zipfile.ZipFile(archive) as zf:
        members = zf.infolist()
        if [member.filename for member in members] != expected:
            raise ValueError("ZIP entries differ from the sorted release manifest")
        if zf.testzip() is not None:
            raise ValueError("ZIP integrity check failed")
        for member, relative in zip(members, files):
            if member.is_dir() or member.date_time != FIXED_TIME or member.external_attr >> 16 != 0o100644:
                raise ValueError(f"Unexpected ZIP metadata: {member.filename}")
            if zf.read(member) != (ROOT / relative).read_bytes():
                raise ValueError(f"Stale ZIP source bytes: {member.filename}")
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    sidecar = archive.with_suffix(".zip.sha256")
    if sidecar.read_text(encoding="ascii") != f"{digest}  {archive.name}\n":
        raise ValueError("SHA-256 sidecar mismatch")
    return digest


def build(archive: Path, files: list[Path]) -> None:
    archive.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(prefix=".release-", suffix=".zip", dir=archive.parent, delete=False) as handle:
        temporary = Path(handle.name)
    try:
        with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
            for relative in files:
                info = zipfile.ZipInfo(f"{SLUG}/{relative.as_posix()}", FIXED_TIME)
                info.create_system = 3
                info.external_attr = 0o100644 << 16
                info.compress_type = zipfile.ZIP_DEFLATED
                zf.writestr(info, (ROOT / relative).read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        os.replace(temporary, archive)
    finally:
        temporary.unlink(missing_ok=True)
    archive.chmod(0o644)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix(".zip.sha256").write_text(f"{digest}  {archive.name}\n", encoding="ascii")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=("build", "verify"))
    parser.add_argument("--archive", type=Path, help="ZIP to verify; its name must match the current plugin version")
    args = parser.parse_args()
    try:
        files, version = release_files()
        archive = args.archive.resolve() if args.archive else archive_path(version)
        if archive.name != archive_path(version).name:
            raise ValueError("ZIP name does not match the current plugin version")
        if args.command == "build":
            if args.archive:
                raise ValueError("--archive applies to verify only")
            build(archive, files)
        print(f"{archive}: SHA-256 {verify(archive, files)}")
    except (OSError, UnicodeError, ValueError, zipfile.BadZipFile) as exc:
        parser.exit(1, f"release error: {exc}\n")


if __name__ == "__main__":
    main()
