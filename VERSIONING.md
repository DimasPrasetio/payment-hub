# Versioning

Project ini memakai dua jenis versi yang terpisah:

- release version: versi aplikasi yang mengikuti Semantic Versioning, disimpan di file root [`VERSION`](./VERSION), dan dirilis sebagai git tag `v{version}`
- API version: versi kontrak HTTP publik yang diekspos lewat prefix route seperti `/api/v1`

## 1. Rules

- Gunakan Semantic Versioning untuk release version: `MAJOR.MINOR.PATCH`
- Naikkan `PATCH` untuk bug fix, hardening, atau perubahan internal yang tidak mengubah kontrak API
- Naikkan `MINOR` untuk penambahan fitur atau endpoint baru yang tetap backward compatible
- Naikkan `MAJOR` hanya untuk perubahan breaking terhadap perilaku aplikasi atau kontrak publik yang memang tidak kompatibel
- Gunakan pre-release bila perlu, misalnya `1.1.0-rc.1` atau `1.1.0-beta.1`

## 2. API Version Policy

- Prefix route API saat ini adalah `v1`
- Jangan ubah API version hanya karena release application berubah
- Buat `v2` hanya saat kontrak publik benar-benar breaking, misalnya path, request, response, auth, atau semantics berubah tidak kompatibel
- Saat ada `v2`, pertahankan `v1` selama masa transisi yang jelas

## 3. Source of Truth

- release version runtime dibaca dari file root [`VERSION`](./VERSION)
- health endpoint mengembalikan `version` untuk release version dan `api_version` untuk contract version
- route prefix API dibaca dari `config/versioning.php`

## 4. Release Flow

1. Tentukan versi berikutnya sesuai perubahan
2. Jalankan `npm run build`
3. Ubah file [`VERSION`](./VERSION)
4. Perbarui `CHANGELOG.md`
5. Commit perubahan release, termasuk `public/build` bila berubah
6. Buat tag annotated: `git tag -a v{version} -m "Release v{version}"`
7. Push branch dan tag: `git push origin main --follow-tags`

## 5. Current Convention

- git branch utama: `main`
- tag release stabil: `v1.0.0`, `v1.0.1`, `v1.1.0`
- tag pre-release: `v1.1.0-rc.1`, `v1.1.0-beta.1`

## 6. What Not To Do

- jangan hardcode release version di controller, test, atau dokumentasi contoh response
- jangan mencampur release version dan API version sebagai satu hal yang sama
- jangan menaikkan API version untuk perubahan internal yang tidak mengubah kontrak publik
