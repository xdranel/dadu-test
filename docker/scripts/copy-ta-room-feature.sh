#!/usr/bin/env bash
# Copy only reviewed TA/room feature paths, preserving replaced files in /tmp.
set -euo pipefail
script_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
source_root="$(realpath -e -- "${1:?Isi folder sumber hasil pengembangan}")"
target_root="$(realpath -e -- "${2:?Isi folder tujuan}")"
file_list="$script_root/docs/112-daftar-file-salin-sidang-ta-room.txt"
[[ "$source_root" != "$target_root" ]] || { echo 'Sumber dan tujuan harus berbeda.' >&2; exit 1; }
# Validate the whole list before copying any file.
while IFS= read -r feature_path || [[ -n "$feature_path" ]]; do
    [[ -z "$feature_path" || "$feature_path" == \#* ]] && continue
    [[ "$feature_path" =~ ^(app/|config/|database/migrations/|resources/views/|routes/|tests/|docs/|docker/scripts/copy-ta-room-feature\.sh$) ]] || { echo "Path ditolak: $feature_path" >&2; exit 1; }
    [[ "$feature_path" != *'..'* && "$feature_path" != /* ]] || exit 1
    [[ -f "$source_root/$feature_path" && ! -L "$source_root/$feature_path" ]] || { echo "Sumber tidak ada/symlink: $feature_path" >&2; exit 1; }
    [[ "$(realpath -m -- "$source_root/$feature_path")" == "$source_root/"* && "$(realpath -m -- "$target_root/$feature_path")" == "$target_root/"* ]] || { echo 'Path keluar dari folder.' >&2; exit 1; }
done < "$file_list"
# Reject changed target code rather than overwriting a different branch or local edits.
php -r '
$source=$argv[1];$target=$argv[2];$manifest=json_decode(file_get_contents($source."/docs/112-manifest-file-salin.json"),true,512,JSON_THROW_ON_ERROR);
foreach($manifest["files"] as $entry){
 $path=$entry["path"]; $expected=$entry["worktree_hash"];
 if(hash_file("sha256",$source."/".$path)!==$expected){fwrite(STDERR,"Sumber berubah: ".$path."\n");exit(1);}
 if(is_file($target."/".$path)){ $actual=hash_file("sha256",$target."/".$path); if($actual!==$expected && $actual!==$entry["docker_before"]){fwrite(STDERR,"Tujuan memiliki perubahan lain: ".$path.". Hentikan dan gabungkan dahulu.\n");exit(1);} }
}
' "$source_root" "$target_root"
backup_root="$(mktemp -d /tmp/dadu112-copy.XXXXXXXX)"
count=0
while IFS= read -r feature_path || [[ -n "$feature_path" ]]; do
    [[ -z "$feature_path" || "$feature_path" == \#* ]] && continue
    if [[ -f "$target_root/$feature_path" ]]; then
        mkdir -p -- "$backup_root/$(dirname -- "$feature_path")"
        cp --preserve=mode,timestamps -- "$target_root/$feature_path" "$backup_root/$feature_path"
    else
        printf '%s\n' "$feature_path" >> "$backup_root/new-files.txt"
    fi
    mkdir -p -- "$target_root/$(dirname -- "$feature_path")"
    cp -- "$source_root/$feature_path" "$target_root/$feature_path"
    if [[ "$feature_path" == *.sh ]]; then chmod 0755 "$target_root/$feature_path"; else chmod 0644 "$target_root/$feature_path"; fi
    cmp --silent -- "$source_root/$feature_path" "$target_root/$feature_path"
    count=$((count + 1))
done < "$file_list"
printf 'Copied and verified %s files. Backup: %s\n' "$count" "$backup_root"
