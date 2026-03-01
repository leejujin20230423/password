# PASS One-Page Build

`index.php` 한 파일에 PHP + HTML + JS + CSS가 모두 들어간 버전입니다.

## 1) 준비
- PHP 8.1+
- MySQL
- 기존 PASS DB 스키마 (`users`, `password` 테이블)

## 2) 설정
1. `onepage_build/.env.example` 를 참고해서 프로젝트 루트 `.env` 또는 `onepage_build/.env`에 DB 값을 맞춥니다.
2. 암호화 키도 운영 시 반드시 변경하세요.

## 3) 실행
```bash
cd onepage_build
php -S 127.0.0.1:8080
```
브라우저에서 `http://127.0.0.1:8080` 접속.

## 참고
- 로그인 성공 시 `users.password`가 평문이면 자동으로 해시로 업그레이드합니다.
- 비밀번호 보관은 `password.encrypted_password`에 AES-256-CBC(base64)로 저장합니다.
