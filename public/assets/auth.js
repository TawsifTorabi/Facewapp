class Auth {
  static api() {
    return API_BASE;
  }

  static token() {
    return localStorage.getItem("token");
  }

  static headers() {
    const token = this.token();

    return token
      ? {
          Authorization: "Bearer " + token,
        }
      : {};
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
  // TOKEN LOGIN CHECK
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

      return await res.json();
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
    localStorage.removeItem("user_id"); // optional if still used elsewhere

    window.location.href = "/login.php";
  }

  static headers() {
    const token = localStorage.getItem("token");

    if (!token) {
      return {};
    }

    return {
      Authorization: "Bearer " + token,
    };
  }
}
