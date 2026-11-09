"use client";

import { useActionState } from "react";
import { createArea, createUser, FormResult, updateParty, updateUser } from "@/app/actions/admin";

function Result({ result }: { result: FormResult | null }) {
  if (!result) return null;
  return <p className={result.ok ? "success" : "error"} role="status">{result.message}</p>;
}

const ROLE_OPTIONS = [
  ["UPLOADER", "Uploader"],
  ["SUPERVISOR", "Supervisor"],
  ["ADMIN", "Admin"],
];

export function NewUserForm() {
  const [result, action, pending] = useActionState(createUser, null);
  return (
    <form action={action} className="card">
      <h2>Add user</h2>
      <Result result={result} />
      <div className="grid-auto">
        <div className="field"><label htmlFor="u-name">Name</label><input id="u-name" name="name" className="input" required maxLength={120} /></div>
        <div className="field"><label htmlFor="u-phone">Phone number</label><input id="u-phone" name="phone" className="input" inputMode="tel" required maxLength={15} /></div>
        <div className="field"><label htmlFor="u-password">Password</label><input id="u-password" name="password" type="password" className="input" required minLength={8} autoComplete="new-password" /></div>
        <div className="field">
          <label htmlFor="u-role">Role</label>
          <select id="u-role" name="role" className="input" defaultValue="UPLOADER">
            {ROLE_OPTIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </div>
      </div>
      <div><button className="btn primary" disabled={pending}>Add user</button></div>
    </form>
  );
}

export function UserRowForm({ id, name, phone, role, active }: { id: number; name: string; phone: string; role: string; active: boolean }) {
  const [result, action, pending] = useActionState(updateUser, null);
  return (
    <form action={action} className="card">
      <input type="hidden" name="id" value={id} />
      <div className="row between">
        <div><strong>{name}</strong><p className="muted">{phone}</p></div>
        <label className="row"><input type="checkbox" name="active" defaultChecked={active} /> Active</label>
      </div>
      <Result result={result} />
      <div className="grid-auto">
        <div className="field">
          <label htmlFor={`role-${id}`}>Role</label>
          <select id={`role-${id}`} name="role" className="input" defaultValue={role}>
            {ROLE_OPTIONS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </div>
        <div className="field">
          <label htmlFor={`password-${id}`}>New password (optional)</label>
          <input id={`password-${id}`} name="password" type="password" className="input" minLength={8} autoComplete="new-password" />
        </div>
      </div>
      <div><button className="btn" disabled={pending}>Save</button></div>
    </form>
  );
}

export function NewAreaForm() {
  const [result, action, pending] = useActionState(createArea, null);
  return (
    <form action={action} className="card">
      <h2>Add area</h2>
      <Result result={result} />
      <div className="field"><label htmlFor="a-name">Area name</label><input id="a-name" name="name" className="input" required maxLength={100} /></div>
      <div><button className="btn primary" disabled={pending}>Add area</button></div>
    </form>
  );
}

export function PartyRowForm({ id, name, source, areaId, active, areas }: { id: number; name: string; source: string; areaId: number | null; active: boolean; areas: { id: number; name: string }[] }) {
  const [result, action, pending] = useActionState(updateParty, null);
  return (
    <form action={action} className="card">
      <input type="hidden" name="id" value={id} />
      <Result result={result} />
      <div className="grid-auto">
        <div className="field"><label htmlFor={`p-name-${id}`}>Display name</label><input id={`p-name-${id}`} name="name" className="input" defaultValue={name} required maxLength={120} /></div>
        <div className="field">
          <label htmlFor={`p-area-${id}`}>Area</label>
          <select id={`p-area-${id}`} name="area" className="input" defaultValue={areaId ?? ""}>
            <option value="">None</option>
            {areas.map((area) => <option key={area.id} value={area.id}>{area.name}</option>)}
          </select>
        </div>
      </div>
      <div className="row between">
        <span className="muted">Upload name: {source}</span>
        <span className="row">
          <label className="row"><input type="checkbox" name="active" defaultChecked={active} /> Active</label>
          <button className="btn" disabled={pending}>Save</button>
        </span>
      </div>
    </form>
  );
}
