#!/usr/bin/env python3
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib
import shutil

root = Path(__file__).resolve().parents[1]
dist = root / 'dist'
plugin_file = (root / 'tazkar.php').read_text(encoding='utf-8')
version_line = next(line for line in plugin_file.splitlines() if line.startswith("define( 'TAZKAR_VERSION'"))
version = version_line.split("'")[3]
output = dist / f'tazkar-{version}.zip'

exclude_parts = {
    '.git', '.github', '.phpunit.cache', 'bin', 'dist', 'tests', 'vendor'
}
exclude_names = {
    'composer.lock', 'phpcs.xml.dist', 'phpstan.neon.dist', 'phpunit.xml.dist'
}

if dist.exists():
    shutil.rmtree(dist)
dist.mkdir(parents=True)

files = []
for path in root.rglob('*'):
    if not path.is_file():
        continue
    relative = path.relative_to(root)
    if any(part in exclude_parts for part in relative.parts):
        continue
    if path.name in exclude_names:
        continue
    files.append(path)

with ZipFile(output, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(files, key=lambda item: item.as_posix()):
        relative = Path('tazkar') / path.relative_to(root)
        info = ZipInfo(relative.as_posix(), date_time=(2020, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = (0o100644 & 0xFFFF) << 16
        archive.writestr(info, path.read_bytes())

digest = hashlib.sha256(output.read_bytes()).hexdigest()
(output.with_suffix(output.suffix + '.sha256')).write_text(
    f'{digest}  {output.name}\n', encoding='utf-8'
)
print(f'Built {output}')
