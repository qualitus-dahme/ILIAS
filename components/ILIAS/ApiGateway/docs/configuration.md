# Configuration

The webservice settings can be configured from the administration dashboard. Here is a breakdown of what each setting does.

## Secret Key

This is a private key used to sign and verify the authenticity of access and refresh tokens. It ensures that tokens were issued by this ILIAS instance and have not been tampered with.

- **Usage:** Used for signing tokens. **Do NOT share it with anyone.**
- **On Fresh Installation:** The field will be blank.
- **Generating a Key:** If the field is left blank and the settings are saved, a new, cryptographically secure random key will be generated.
- **System Impact:** Changing this key will immediately invalidate **all** previously issued access and refresh tokens. All users and applications will be forced to re-authenticate to get new tokens. This action is irreversible.

## Signing Algorithm

This setting determines the algorithm used to create the digital signature for the JSON Web Tokens (JWTs) that serve as access and refresh tokens.

- **What it is:** A standard method for creating a secure signature.
- **How it's used:** When a token is issued, this algorithm uses the **Secret Key** to create a unique signature for the token's content. When the token is later used to access the API, the system uses the same key and algorithm to verify that the signature is still valid, proving the token is authentic.
- **System Impact:** A more complex algorithm (e.g., HS512 vs. HS256) provides higher security against brute-force attacks but requires slightly more server CPU resources to sign and verify tokens. The impact on performance is usually negligible for most systems.

## Hashing Algorithm

This setting determines the algorithm used to securely store refresh tokens in the database.

- **What it is:** A one-way function that turns a token into a fixed-size string of characters, called a hash.
- **How it's used:** Instead of storing the actual refresh token in the database (which would be a security risk if the database was compromised), we store its hash. When a user tries to use a refresh token, we hash it and compare it to the stored hash. This allows us to verify the token without ever storing the token itself.
- **System Impact:** A stronger algorithm (e.g., SHA-512 vs. SHA-256) produces a longer hash, offering better security. This requires slightly more storage space in the database per token and a tiny bit more CPU to compute the hash, but the security benefits generally outweigh the cost.

## Access Token Expiry

This is the lifespan of an access token, in seconds. An access token is what applications use to make API calls on behalf of a user.

- **How it's used:** After this time has passed, the access token can no longer be used to access protected API endpoints. The application must then use a refresh token to get a new access token.
- **Default:** 86400 seconds.
- **Example:** With the default value, an access token issued at 10:00 AM on Monday will expire at 10:00 AM on Tuesday (86,400 seconds = 24 hours = 1 day).
- **System Impact:**
  - **Shorter expiry (e.g., 900 seconds / 15 minutes):** More secure. If a token is stolen, it's only useful for a short time. However, this forces applications to refresh tokens more often, causing more API calls to the refresh endpoint.
  - **Longer expiry (e.g., 86400 seconds / 1 day):** Less secure, as a stolen token is valid for longer. It is more convenient for simple applications as they don't need to handle token refreshing as frequently.

## Refresh Token Expiry

This is the lifespan of a refresh token, in seconds. A refresh token's only purpose is to get a new access token when the old one expires.

- **How it's used:** It allows a user to stay logged in for a longer period without having to enter their password again. As long as the refresh token is valid, the application can obtain new access tokens.
- **Default:** 604800 seconds.
- **Example:** With the default value, a refresh token issued on the 1st of the month will be valid until the 8th of the month (604,800 seconds = 7 days = 1 week).
- **System Impact:**
  - **Shorter expiry (e.g., 1 day):** More secure. If a user's refresh token is compromised, the attacker only has a day to use it. This forces users to log in with their password more frequently.
  - **Longer expiry (e.g., 30 days):** More convenient for users, as they can stay logged in for a month. However, it increases the security risk if a refresh token is stolen.
