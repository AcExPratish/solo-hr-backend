# Access Token and Refresh Token Implementation

This document describes the implementation of access token and refresh token logic in the Solo HR Backend application.

## Overview

The application uses Laravel Passport for OAuth2 authentication with personal access tokens. The implementation includes:

- Access tokens with 1-hour expiration
- Token refresh functionality
- Automatic token expiration warnings via middleware
- Proper token revocation on logout

## Key Components

### 1. AuthServiceProvider Configuration

**File:** `app/Providers/AuthServiceProvider.php`

- Access tokens expire in 1 hour
- Refresh tokens expire in 30 days (for future OAuth2 implementation)
- Personal access tokens expire in 1 year

### 2. AuthController Updates

**File:** `app/Http/Controllers/AuthController.php`

#### Login Method
- Returns access token with proper metadata
- Revokes existing tokens before creating new ones
- Includes token type and expiration information

#### Refresh Token Method
- Requires authentication (user must be logged in)
- Revokes current token and creates a new one
- Returns new access token with metadata

### 3. Token Refresh Middleware

**File:** `app/Http/Middleware/TokenRefreshMiddleware.php`

- Monitors token expiration
- Adds headers when token is close to expiring (within 5 minutes)
- Headers:
  - `X-Token-Refresh-Required: true`
  - `X-Token-Expires-At: [ISO timestamp]`

### 4. API Routes

**File:** `routes/api.php`

- Refresh endpoint requires authentication
- All authenticated routes use the token refresh middleware
- Proper route grouping for auth and non-auth endpoints

## API Endpoints

### Authentication Endpoints

```
POST /api/v1/auth/login
POST /api/v1/auth/register
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
```

### Protected Endpoints (Require Authentication)

```
GET  /api/v1/auth/me
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
```

## Token Response Format

### Login Response
```json
{
  "success": true,
  "message": "User logged in successfully",
  "code": 200,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJh...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### Refresh Token Response
```json
{
  "success": true,
  "message": "Token refreshed successfully",
  "code": 200,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJh...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

## Security Features

1. **Token Revocation**: All existing tokens are revoked when user logs in or refreshes token
2. **Short Token Lifespan**: Access tokens expire in 1 hour for security
3. **Automatic Expiration Warnings**: Middleware warns clients when tokens are about to expire
4. **Proper Authentication**: Refresh endpoint requires valid authentication

## Usage Instructions

### For Frontend Applications

1. **Login**: Send credentials to `/api/v1/auth/login`
2. **Store Token**: Save the access token securely
3. **API Requests**: Include token in Authorization header: `Bearer [token]`
4. **Monitor Headers**: Check for `X-Token-Refresh-Required` header
5. **Refresh Token**: When needed, call `/api/v1/auth/refresh` with current token
6. **Logout**: Call `/api/v1/auth/logout` to revoke tokens

### Example Frontend Implementation

```javascript
// Login
const loginResponse = await fetch('/api/v1/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password })
});

const { data } = await loginResponse.json();
localStorage.setItem('access_token', data.access_token);

// API Request with token
const apiResponse = await fetch('/api/v1/auth/me', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('access_token')}`
  }
});

// Check for refresh requirement
if (apiResponse.headers.get('X-Token-Refresh-Required') === 'true') {
  const refreshResponse = await fetch('/api/v1/auth/refresh', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('access_token')}`
    }
  });
  
  const { data: refreshData } = await refreshResponse.json();
  localStorage.setItem('access_token', refreshData.access_token);
}
```

## Future Enhancements

1. **OAuth2 Authorization Code Flow**: Implement full OAuth2 with refresh tokens
2. **Token Blacklisting**: Add token blacklisting for enhanced security
3. **Refresh Token Rotation**: Implement refresh token rotation
4. **Device Management**: Track and manage multiple devices per user
5. **Token Scopes**: Implement token scopes for fine-grained permissions

## Notes

- The current implementation uses Laravel Passport's personal access tokens
- Lint warnings about undefined methods are false positives - the methods exist in Passport
- The middleware provides proactive token refresh warnings
- All tokens are properly revoked on logout for security
