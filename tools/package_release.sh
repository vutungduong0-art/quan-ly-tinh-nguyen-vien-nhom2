#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$project_root"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Không thể đóng gói: thư mục hiện tại chưa phải Git repository." >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Không thể đóng gói: còn thay đổi đã theo dõi nhưng chưa commit." >&2
  exit 1
fi

version="$(tr -d '[:space:]' < VERSION)"
if [[ ! "$version" =~ ^[0-9A-Za-z][0-9A-Za-z._-]*$ ]]; then
  echo "VERSION không hợp lệ: $version" >&2
  exit 1
fi

release_dir="$project_root/release"
archive="$release_dir/volunteerhub-$version.tar.gz"
checksum="$archive.sha256"
mkdir -p "$release_dir"

git archive \
  --format=tar.gz \
  --prefix="volunteerhub-$version/" \
  --output="$archive" \
  HEAD

if tar -tzf "$archive" | grep -Eq '/(app/\.local|\.git|release)/'; then
  rm -f "$archive"
  echo "Gói phát hành chứa đường dẫn bị cấm; đã hủy gói." >&2
  exit 1
fi

sha256sum "$archive" > "$checksum"
echo "Đã tạo $archive"
echo "Đã tạo $checksum"
