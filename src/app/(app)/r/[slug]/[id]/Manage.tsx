"use client";

import { useActionState, useState } from "react";
import { correctAction, deleteAction, restoreAction } from "@/app/actions/documents";

interface Values {
  date: string;
  party: string;
  amount: number;
  items: number | null;
  receipt: string;
  mr: string;
  areaId: number | null;
  address: string;
}

interface Props {
  documentId: number;
  slug: string;
  partyLabel: string;
  values: Values;
  areas: { id: number; name: string }[];
  showItems: boolean;
  showReceipt: boolean;
  showLocation: boolean;
}

export function CorrectionForm({ documentId, slug, partyLabel, values, areas, showItems, showReceipt, showLocation }: Props) {
  const [result, action, pending] = useActionState(correctAction.bind(null, documentId), null);
  return (
    <details className="card">
      <summary><strong>Correct details or delete</strong></summary>
      <form action={action} className="field" style={{ gap: 10 }}>
        {result && <p className={result.ok ? "success" : "error"} role="status">{result.message}</p>}
        <div className="field"><label htmlFor="c-date">Date</label><input id="c-date" name="date" type="date" className="input" defaultValue={values.date} required /></div>
        <div className="field"><label htmlFor="c-party">{partyLabel}</label><input id="c-party" name="party" className="input" defaultValue={values.party} required maxLength={200} /></div>
        <div className="field"><label htmlFor="c-amount">Amount</label><input id="c-amount" name="amount" className="input" inputMode="decimal" defaultValue={values.amount} required /></div>
        {showItems && <div className="field"><label htmlFor="c-items">No of Item</label><input id="c-items" name="items" className="input" inputMode="numeric" defaultValue={values.items ?? ""} /></div>}
        {showReceipt && <div className="field"><label htmlFor="c-receipt">Receipt</label><input id="c-receipt" name="receipt" className="input" defaultValue={values.receipt} maxLength={50} /></div>}
        {showLocation && (
          <>
            <div className="field"><label htmlFor="c-mr">MR</label><input id="c-mr" name="mr" className="input" defaultValue={values.mr} maxLength={120} /></div>
            <div className="field">
              <label htmlFor="c-area">Area</label>
              <select id="c-area" name="area" className="input" defaultValue={values.areaId ?? ""}>
                <option value="">None</option>
                {areas.map((area) => <option key={area.id} value={area.id}>{area.name}</option>)}
              </select>
            </div>
            <div className="field"><label htmlFor="c-address">Address</label><input id="c-address" name="address" className="input" defaultValue={values.address} maxLength={255} /></div>
          </>
        )}
        <div className="row between">
          <button className="btn primary" disabled={pending}>{pending ? "Saving..." : "Save correction"}</button>
          <DeleteButton documentId={documentId} slug={slug} />
        </div>
      </form>
    </details>
  );
}

function DeleteButton({ documentId, slug }: { documentId: number; slug: string }) {
  const [confirming, setConfirming] = useState(false);
  if (!confirming) return <button type="button" className="btn danger" onClick={() => setConfirming(true)}>Delete document</button>;
  return (
    <span className="row">
      <button type="button" className="btn" onClick={() => setConfirming(false)}>Keep</button>
      <button type="button" className="btn danger" onClick={() => deleteAction(documentId, slug)}>Confirm delete</button>
    </span>
  );
}

export function RestoreButton({ documentId, slug }: { documentId: number; slug: string }) {
  const [message, setMessage] = useState("");
  return (
    <div className="row">
      <button
        type="button"
        className="btn primary"
        onClick={async () => {
          const result = await restoreAction(documentId, slug);
          if (result && !result.ok) setMessage(result.message);
        }}
      >
        Restore document
      </button>
      {message && <span className="error">{message}</span>}
    </div>
  );
}
