"use client";

import { useActionState } from "react";
import { login } from "@/app/actions/auth";

export function LoginForm() {
  const [error, action, pending] = useActionState(login, null);
  return (
    <form action={action} className="card">
      <h1>Sign in</h1>
      {error && <p className="error" role="alert">{error}</p>}
      <div className="field">
        <label htmlFor="phone">Phone number</label>
        <input id="phone" name="phone" className="input" inputMode="tel" autoComplete="username" required />
      </div>
      <div className="field">
        <label htmlFor="password">Password</label>
        <input id="password" name="password" className="input" type="password" autoComplete="current-password" required />
      </div>
      <button className="btn primary" disabled={pending}>{pending ? "Signing in..." : "Sign in"}</button>
    </form>
  );
}
