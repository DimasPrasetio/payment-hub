# Changelog

Semua perubahan penting pada project ini dicatat di file ini.

Format yang dipakai mengikuti prinsip Keep a Changelog dan Semantic Versioning.

## [Unreleased]

## [1.0.1] - 2026-03-13

### Added

- sistem versioning terpusat melalui file `VERSION` untuk release runtime dan helper script release di `scripts/release.ps1` serta `scripts/release.sh`
- health endpoint sekarang mengembalikan `api_version` terpisah dari `version` release aplikasi

### Changed

- prefix API sekarang dibaca dari konfigurasi versioning, bukan string hardcoded di route file
- dokumentasi release dan versioning sekarang dibakukan melalui `VERSIONING.md` dan `CHANGELOG.md`

## [1.0.0] - 2026-03-13

### Added

- stable release pertama untuk Payment Hub dengan admin panel, client API, provider callback, dan webhook outbound

### Changed

- release artifact frontend `public/build` sekarang ikut dilacak di repository untuk mempermudah deploy shared hosting

### Fixed

- create payment sekarang menangani race condition idempotency dengan lebih aman saat request paralel
