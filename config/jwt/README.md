# JWT keys for Lexik

This folder must contain:

- `private.pem` — RSA private key (used to sign tokens)
- `public.pem` — RSA public key (used to verify tokens)

## Generate keys

From project root:

```bash
php bin/console lexik:jwt:generate-keypair
```

If that fails on Windows with an OpenSSL error ("No such process" or "openssl not recognized"):

1. **Option A — Node.js (one-liner):** If Node is installed, from project root:
   ```bash
   node -e "const c=require('crypto');const {privateKey,publicKey}=c.generateKeyPairSync('rsa',{modulusLength:2048});require('fs').writeFileSync('config/jwt/private.pem',privateKey.export({type:'pkcs1',format:'pem'}));require('fs').writeFileSync('config/jwt/public.pem',publicKey.export({type:'spki',format:'pem'}));console.log('JWT keys created.');"
   ```
2. **Option B — WSL:** Open WSL and run the Lexik command from the project directory.
3. **Option C — OpenSSL for Windows:** Install [Win64 OpenSSL](https://slproweb.com/products/Win32OpenSSL.html), add it to your PATH, then run the Lexik command again.
4. **Option D — Manual OpenSSL:** If `openssl` is in your PATH:
   ```bash
   openssl genrsa -out config/jwt/private.pem -aes256 2048
   openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
   ```
   Use an empty passphrase for dev, and set `JWT_PASSPHRASE=` in `.env`. If you set a passphrase, put it in `.env` as `JWT_PASSPHRASE=yourpassphrase`.

Do not commit real keys to version control. Add `config/jwt/*.pem` to `.gitignore` if needed.
