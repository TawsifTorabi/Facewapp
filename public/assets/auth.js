class Auth {
  static api() {
    return API_BASE;
  }

  static token() {
    return localStorage.getItem("token");
  }

  static headers() {
    const token = this.token();

    return token ? { Authorization: "Bearer " + token } : {};
  }

  // =====================
  // BOOTSTRAP SSO LOGIN
  // =====================
  static async bootstrapSSO() {
    const urlParams = new URLSearchParams(window.location.search);
    const ssoToken = urlParams.get("token");

    if (!ssoToken) return;

    try {
      const res = await fetch(`${this.api()}?action=exchange_token`, {
        headers: {
          Authorization: "Bearer " + ssoToken,
        },
      });

      const data = await res.json();

      if (data.token) {
        localStorage.setItem("token", data.token);

        // clean URL (important UX fix)
        window.history.replaceState(
          {},
          document.title,
          window.location.pathname,
        );
      }
    } catch (err) {
      console.error("SSO bootstrap failed:", err);
      window.location.href = "/login.php";
    }
  }

  // =====================
  // SESSION REFRESH
  // =====================

  static startAutoRefresh() {
    setInterval(async () => {
      const token = this.token();
      if (!token) return;

      try {
        const res = await fetch(`${this.api()}?action=refresh_token`, {
          headers: {
            Authorization: "Bearer " + token,
          },
        });

        const data = await res.json();

        if (data.token) {
          localStorage.setItem("token", data.token);
          console.log("Token refreshed");
        }
      } catch (err) {
        console.warn("Token refresh failed", err);
      }
    }, 300 * 1000); // every 5 Minutes refresh the Auth Token
  }

  // =====================
  // SESSION LOGIN (COOKIE BASED)
  // =====================
  static async sessionLogin() {
    try {
      const res = await fetch(`${this.api()}?action=session_login`, {
        credentials: "include",
      });

      return await res.json();
    } catch (err) {
      console.error("Session login failed:", err);
      return { logged_in: false };
    }
  }

  // =====================
  // TOKEN CHECK (SAFE VERSION)
  // =====================
  static async checkLogin() {
    const token = this.token();

    if (!token) {
      return { logged_in: false };
    }

    try {
      const res = await fetch(`${this.api()}?action=check_login`, {
        headers: this.headers(),
      });

      const data = await res.json();

      // 🔥 auto logout if invalid token
      if (!data.logged_in) {
        this.logout();
      }

      return data;
    } catch (err) {
      console.error("Check login failed:", err);
      return { logged_in: false };
    }
  }

  // =====================
  // LOGOUT
  // =====================
  static logout() {
    localStorage.removeItem("token");
    localStorage.removeItem("user_id");

    window.location.href = "/login.php";
  }
}
