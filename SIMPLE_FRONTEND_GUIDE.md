# Simple Frontend API Guide (No CSRF)

## 🚀 **Simple Token-Based Authentication**

Your Laravel API now uses simple Bearer token authentication without CSRF protection.

## 📡 **API Endpoints**

### Authentication

```
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/register
```

### Events

```
GET /api/v1/events                    # Public: Published events only
GET /api/v1/events/{id}              # Public: Published events only
GET /api/v1/admin/events             # Admin: All events
POST /api/v1/admin/events            # Admin: Create event
PUT /api/v1/admin/events/{id}        # Admin: Update event
DELETE /api/v1/admin/events/{id}     # Admin: Delete event
PATCH /api/v1/admin/events/{id}/status # Admin: Update status
```

## 🔧 **Frontend Implementation**

### 1. **Auth Service (Simple)**

```javascript
// services/authService.js
class AuthService {
    static async login(email, password) {
        try {
            const response = await fetch(
                "http://127.0.0.1:8000/api/v1/auth/login",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ email, password }),
                }
            );

            const data = await response.json();

            if (data.success) {
                // Save token to localStorage
                localStorage.setItem("auth_token", data.data.token);
                console.log("Login successful:", data);
                return data;
            }

            throw new Error(data.message || "Login failed");
        } catch (error) {
            console.error("Login error:", error);
            throw error;
        }
    }

    static async logout() {
        try {
            const token = localStorage.getItem("auth_token");
            const response = await fetch(
                "http://127.0.0.1:8000/api/v1/auth/logout",
                {
                    method: "POST",
                    headers: {
                        Authorization: `Bearer ${token}`,
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                }
            );

            localStorage.removeItem("auth_token");
            return response.json();
        } catch (error) {
            console.error("Logout error:", error);
            throw error;
        }
    }

    static getToken() {
        return localStorage.getItem("auth_token");
    }

    static isAuthenticated() {
        return !!this.getToken();
    }
}

export default AuthService;
```

### 2. **API Service (Simple)**

```javascript
// services/apiService.js
import AuthService from "./authService";

class ApiService {
    static async request(url, options = {}) {
        const token = AuthService.getToken();

        const defaultOptions = {
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...(token && { Authorization: `Bearer ${token}` }),
            },
        };

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

    // Public Event API calls
    static async getEvents() {
        return this.request("http://127.0.0.1:8000/api/v1/events");
    }

    static async getEvent(id) {
        return this.request(`http://127.0.0.1:8000/api/v1/events/${id}`);
    }

    // Admin Event API calls
    static async getAdminEvents() {
        return this.request("http://127.0.0.1:8000/api/v1/admin/events");
    }

    static async createEvent(eventData) {
        return this.request("http://127.0.0.1:8000/api/v1/admin/events", {
            method: "POST",
            body: JSON.stringify(eventData),
        });
    }

    static async updateEvent(id, eventData) {
        return this.request(`http://127.0.0.1:8000/api/v1/admin/events/${id}`, {
            method: "PUT",
            body: JSON.stringify(eventData),
        });
    }

    static async deleteEvent(id) {
        return this.request(`http://127.0.0.1:8000/api/v1/admin/events/${id}`, {
            method: "DELETE",
        });
    }

    static async updateEventStatus(id, status) {
        return this.request(
            `http://127.0.0.1:8000/api/v1/admin/events/${id}/status`,
            {
                method: "PATCH",
                body: JSON.stringify({ status }),
            }
        );
    }

    // Contact Requests
    static async submitContactRequest(data) {
        return this.request("http://127.0.0.1:8000/api/v1/contact-requests", {
            method: "POST",
            body: JSON.stringify(data),
        });
    }

    // Applications
    static async submitApplication(data) {
        return this.request("http://127.0.0.1:8000/api/v1/applications", {
            method: "POST",
            body: JSON.stringify(data),
        });
    }
}

export default ApiService;
```

### 3. **React Login Component**

```jsx
// components/LoginForm.jsx
import React, { useState } from "react";
import AuthService from "../services/authService";

const LoginForm = () => {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const result = await AuthService.login(email, password);
            console.log("Login successful:", result);
            // Redirect to admin dashboard
            window.location.href = "/admin/events";
        } catch (error) {
            console.error("Login failed:", error);
            alert("Login failed: " + error.message);
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
                {loading ? "Logging in..." : "Login"}
            </button>
        </form>
    );
};

export default LoginForm;
```

### 4. **React Events Component**

```jsx
// components/EventsList.jsx
import React, { useState, useEffect } from "react";
import ApiService from "../services/apiService";
import AuthService from "../services/authService";

const EventsList = () => {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [isAdmin, setIsAdmin] = useState(false);

    useEffect(() => {
        loadEvents();
        setIsAdmin(AuthService.isAuthenticated());
    }, []);

    const loadEvents = async () => {
        try {
            setLoading(true);
            const data = isAdmin
                ? await ApiService.getAdminEvents()
                : await ApiService.getEvents();

            if (data.success) {
                setEvents(data.data.data);
            }
        } catch (error) {
            console.error("Failed to load events:", error);
        } finally {
            setLoading(false);
        }
    };

    const updateEventStatus = async (eventId, status) => {
        try {
            const result = await ApiService.updateEventStatus(eventId, status);
            if (result.success) {
                loadEvents(); // Reload events
                alert(`Event ${status} successfully!`);
            }
        } catch (error) {
            console.error("Failed to update status:", error);
            alert("Failed to update event status");
        }
    };

    if (loading) return <div>Loading events...</div>;

    return (
        <div>
            <h2>{isAdmin ? "All Events (Admin)" : "Published Events"}</h2>
            {events.map((event) => (
                <div
                    key={event.id}
                    style={{
                        border: "1px solid #ccc",
                        margin: "10px",
                        padding: "10px",
                    }}
                >
                    <h3>{event.title}</h3>
                    <p>{event.description}</p>
                    <p>Status: {event.status}</p>
                    <p>
                        Start Date:{" "}
                        {new Date(event.start_date).toLocaleDateString()}
                    </p>

                    {isAdmin && (
                        <div>
                            <button
                                onClick={() =>
                                    updateEventStatus(event.id, "published")
                                }
                            >
                                Publish
                            </button>
                            <button
                                onClick={() =>
                                    updateEventStatus(event.id, "draft")
                                }
                            >
                                Draft
                            </button>
                            <button
                                onClick={() =>
                                    updateEventStatus(event.id, "cancelled")
                                }
                            >
                                Cancel
                            </button>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
};

export default EventsList;
```

## 🧪 **Test Credentials**

```
Email: admin@foundation.com
Password: admin123
```

## 📝 **Key Points**

-   ✅ **No CSRF tokens needed** - Simple Bearer token authentication
-   ✅ **No credentials: 'include'** - No cookies required
-   ✅ **Simple headers** - Just Content-Type and Authorization
-   ✅ **Token in localStorage** - Easy to manage
-   ✅ **Public vs Admin routes** - Clear separation

## 🚀 **Quick Test**

```javascript
// Test login
const testLogin = async () => {
    try {
        const result = await fetch("http://127.0.0.1:8000/api/v1/auth/login", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                email: "admin@foundation.com",
                password: "admin123",
            }),
        });

        const data = await result.json();
        console.log("Login result:", data);
    } catch (error) {
        console.error("Login error:", error);
    }
};

testLogin();
```

This is much simpler - no CSRF, no cookies, just Bearer tokens! 🎉
