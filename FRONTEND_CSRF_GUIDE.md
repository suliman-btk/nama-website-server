# Frontend CSRF Token Handling Guide

## 🔧 **CSRF Token Setup for Laravel Sanctum**

Your Laravel backend is configured with `EnsureFrontendRequestsAreStateful` middleware, which requires CSRF tokens for all POST, PUT, PATCH, and DELETE requests.

## 📡 **API Endpoints**

### CSRF Cookie Endpoint
```
GET /sanctum/csrf-cookie
```
**Purpose:** Gets CSRF token and sets it in a cookie
**Response:** `{"message": "CSRF cookie set"}`

### Authentication Endpoints
```
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/register
```

## 🚀 **Frontend Implementation**

### 1. **CSRF Token Service**

```javascript
// services/csrfService.js
class CsrfService {
  static async getCsrfToken() {
    try {
      const response = await fetch('http://127.0.0.1:8000/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include', // Important: Include cookies
      });
      
      if (response.ok) {
        console.log('CSRF cookie set successfully');
        return true;
      }
      throw new Error('Failed to get CSRF token');
    } catch (error) {
      console.error('CSRF token error:', error);
      return false;
    }
  }
}

export default CsrfService;
```

### 2. **Auth Service with CSRF**

```javascript
// services/authService.js
import CsrfService from './csrfService';

class AuthService {
  static async login(email, password) {
    try {
      // Step 1: Get CSRF token first
      await CsrfService.getCsrfToken();
      
      // Step 2: Make login request
      const response = await fetch('http://127.0.0.1:8000/api/v1/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include', // Important: Include cookies
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();
      
      if (data.success) {
        // Save token to localStorage for API calls
        localStorage.setItem('auth_token', data.data.token);
        console.log('Login successful:', data);
        return data;
      }
      
      throw new Error(data.message || 'Login failed');
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    }
  }

  static async logout() {
    try {
      await CsrfService.getCsrfToken();
      
      const token = localStorage.getItem('auth_token');
      const response = await fetch('http://127.0.0.1:8000/api/v1/auth/logout', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include',
      });

      localStorage.removeItem('auth_token');
      return response.json();
    } catch (error) {
      console.error('Logout error:', error);
      throw error;
    }
  }
}

export default AuthService;
```

### 3. **API Service with CSRF**

```javascript
// services/apiService.js
import CsrfService from './csrfService';

class ApiService {
  static async request(url, options = {}) {
    const token = localStorage.getItem('auth_token');
    
    const defaultOptions = {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(token && { 'Authorization': `Bearer ${token}` }),
      },
      credentials: 'include',
    };

    // For state-changing requests, get CSRF token first
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method)) {
      await CsrfService.getCsrfToken();
    }

    const response = await fetch(url, {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...options.headers,
      },
    });

    return response.json();
  }

  // Event API calls
  static async getEvents() {
    return this.request('http://127.0.0.1:8000/api/v1/events');
  }

  static async createEvent(eventData) {
    return this.request('http://127.0.0.1:8000/api/v1/admin/events', {
      method: 'POST',
      body: JSON.stringify(eventData),
    });
  }

  static async updateEvent(id, eventData) {
    return this.request(`http://127.0.0.1:8000/api/v1/admin/events/${id}`, {
      method: 'PUT',
      body: JSON.stringify(eventData),
    });
  }

  static async deleteEvent(id) {
    return this.request(`http://127.0.0.1:8000/api/v1/admin/events/${id}`, {
      method: 'DELETE',
    });
  }

  static async updateEventStatus(id, status) {
    return this.request(`http://127.0.0.1:8000/api/v1/admin/events/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    });
  }
}

export default ApiService;
```

### 4. **React Component Example**

```jsx
// components/LoginForm.jsx
import React, { useState } from 'react';
import AuthService from '../services/authService';

const LoginForm = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const result = await AuthService.login(email, password);
      console.log('Login successful:', result);
      // Redirect to admin dashboard
      window.location.href = '/admin/events';
    } catch (error) {
      console.error('Login failed:', error);
      alert('Login failed: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Email"
        required
      />
      <input
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="Password"
        required
      />
      <button type="submit" disabled={loading}>
        {loading ? 'Logging in...' : 'Login'}
      </button>
    </form>
  );
};

export default LoginForm;
```

## 🔍 **Debugging Tips**

### Check CSRF Cookie
```javascript
// Check if CSRF cookie is set
console.log('CSRF Cookie:', document.cookie);
```

### Check Network Tab
1. Open browser DevTools
2. Go to Network tab
3. Look for:
   - `GET /sanctum/csrf-cookie` - Should return 200
   - `POST /api/v1/auth/login` - Should include `XSRF-TOKEN` cookie

### Common Issues
1. **Missing `credentials: 'include'`** - Cookies won't be sent
2. **Wrong order** - Must call CSRF endpoint before POST requests
3. **CORS issues** - Make sure CORS is configured for your domain

## 🧪 **Test Credentials**

```
Email: admin@foundation.com
Password: admin123
```

## 📝 **Key Points**

- ✅ Always call `/sanctum/csrf-cookie` before POST/PUT/PATCH/DELETE
- ✅ Use `credentials: 'include'` in all requests
- ✅ CSRF token is automatically included in cookies
- ✅ Bearer token is still needed for API authentication
- ✅ Both CSRF and Bearer tokens work together








