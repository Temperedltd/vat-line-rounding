#!/usr/bin/env bash
set -euo pipefail

validate_version() {
	local version="$1"

	if [[ ! "$version" =~ ^[0-9][0-9A-Za-z.+-]*$ ]]; then
		echo "Unsafe Version header: $version." >&2
		return 1
	fi
}

plugin_version() {
	local plugin_file="$1"
	local version

	version="$(
		sed -nE 's/^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*(.+)$/\1/p' "$plugin_file" |
			head -n 1 |
			sed -E 's/[[:space:]]+$//'
	)"

	if [[ -z "$version" ]]; then
		echo "Could not find Version header in $plugin_file." >&2
		return 1
	fi

	validate_version "$version"

	printf '%s\n' "$version"
}

version_changed() {
	local current_file="$1"
	local previous_file="$2"
	local current_version
	local previous_version

	if [[ ! -f "$previous_file" ]]; then
		printf 'true\n'
		return 0
	fi

	current_version="$(plugin_version "$current_file")"
	previous_version="$(plugin_version "$previous_file")"

	if [[ "$current_version" == "$previous_version" ]]; then
		printf 'false\n'
		return 0
	fi

	printf 'true\n'
}

release_notes() {
	local changelog_file="$1"
	local version="$2"
	local notes

	validate_version "$version"

	notes="$(
		awk -v version="$version" '
			BEGIN {
				heading = "## [" version "]";
			}

			$0 == heading || (index($0, heading " - ") == 1 && $0 ~ /^## \[[^]]+\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$/) {
				found = 1;
				next;
			}

			found && /^## / {
				exit;
			}

			found {
				print;
			}

			END {
				if (! found) {
					exit 42;
				}
			}
		' "$changelog_file"
	)" || {
		echo "Could not find release notes for version $version in $changelog_file." >&2
		return 1
	}

	if [[ -z "${notes//[[:space:]]/}" ]]; then
		echo "Release notes for version $version are empty." >&2
		return 1
	fi

	printf '%s\n' "$notes"
}

copy_required_file() {
	local source_dir="$1"
	local package_dir="$2"
	local file="$3"

	if [[ ! -f "$source_dir/$file" ]]; then
		echo "Required release file is missing: $file." >&2
		return 1
	fi

	cp "$source_dir/$file" "$package_dir/$file"
}

build_zip() {
	local source_dir="$1"
	local output_dir="$2"
	local version="$3"
	local package_name="vat-line-rounding"
	local build_parent="$output_dir/build-$package_name-$version"
	local package_dir="$build_parent/$package_name"
	local zip_file="$output_dir/$package_name-$version.zip"

	validate_version "$version"

	if [[ -e "$build_parent" ]]; then
		echo "Build directory already exists: $build_parent." >&2
		return 1
	fi

	if [[ -e "$zip_file" ]]; then
		echo "Release ZIP already exists: $zip_file." >&2
		return 1
	fi

	mkdir -p "$package_dir"

	copy_required_file "$source_dir" "$package_dir" 'vat-line-rounding.php'
	copy_required_file "$source_dir" "$package_dir" 'README.md'
	copy_required_file "$source_dir" "$package_dir" 'SECURITY.md'
	copy_required_file "$source_dir" "$package_dir" 'CHANGELOG.md'

	if [[ ! -d "$source_dir/includes" ]]; then
		echo "Required release directory is missing: includes." >&2
		return 1
	fi

	cp -R "$source_dir/includes" "$package_dir/includes"

	(
		cd "$build_parent"
		zip -qr "$zip_file" "$package_name"
	)

	printf '%s\n' "$zip_file"
}

usage() {
	cat <<'EOF'
Usage:
  release-build.sh plugin-version <plugin-file>
  release-build.sh version-changed <current-plugin-file> <previous-plugin-file>
  release-build.sh release-notes <changelog-file> <version>
  release-build.sh build-zip <source-dir> <output-dir> <version>
EOF
}

main() {
	local command="${1:-}"

	case "$command" in
		plugin-version)
			[[ $# -eq 2 ]] || {
				usage >&2
				return 64
			}
			plugin_version "$2"
			;;
		version-changed)
			[[ $# -eq 3 ]] || {
				usage >&2
				return 64
			}
			version_changed "$2" "$3"
			;;
		release-notes)
			[[ $# -eq 3 ]] || {
				usage >&2
				return 64
			}
			release_notes "$2" "$3"
			;;
		build-zip)
			[[ $# -eq 4 ]] || {
				usage >&2
				return 64
			}
			build_zip "$2" "$3" "$4"
			;;
		*)
			usage >&2
			return 64
			;;
	esac
}

main "$@"
