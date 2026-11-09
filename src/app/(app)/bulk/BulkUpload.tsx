"use client";

import Link from "next/link";
import { useState } from "react";
import { csvCell } from "@/lib/bulk";

interface RegisterInfo {
  slug: string;
  label: string;
  columns: string[];
}

interface ResultRow {
  row: number;
  no: string;
  date: string;
  party: string;
  amount: string;
  outcome: "ready" | "failed" | "skipped";
  reason: string;
  cells: string[];
}

const SHOWN_ROWS = 200;

export function BulkUpload({ registers, initial }: { registers: RegisterInfo[]; initial: string }) {
  const [slug, setSlug] = useState(initial);
  const [file, setFile] = useState<File | null>(null);
  const [rows, setRows] = useState<ResultRow[] | null>(null);
  const [done, setDone] = useState<{ created: number; skipped: number; failed: number } | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const register = registers.find((item) => item.slug === slug) as RegisterInfo;

  function choose(next: string) {
    setSlug(next);
    setFile(null);
    setRows(null);
    setDone(null);
    setError("");
  }

  async function send(selected: File, mode: "preview" | "confirm") {
    setBusy(true);
    setError("");
    try {
      const form = new FormData();
      form.set("file", selected);
      form.set("mode", mode);
      const response = await fetch(`/api/bulk/${slug}`, { method: "POST", body: form });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error ?? "The file could not be processed.");
      setRows(result.rows);
      if (mode === "confirm") setDone({ created: result.created, skipped: result.skipped, failed: result.failed });
    } catch (problem) {
      setRows(null);
      setError(problem instanceof Error ? problem.message : "The file could not be processed.");
    } finally {
      setBusy(false);
    }
  }

  function picked(event: React.ChangeEvent<HTMLInputElement>) {
    const selected = event.target.files?.[0] ?? null;
    event.target.value = "";
    setFile(selected);
    setDone(null);
    if (selected) void send(selected, "preview");
  }

  function downloadFailed() {
    if (!rows) return;
    const header = ["Row", ...register.columns, "Reason"].map(csvCell).join(",");
    const lines = rows.filter((row) => row.outcome !== "ready").map((row) => [row.row, ...row.cells, row.reason].map(csvCell).join(","));
    const url = URL.createObjectURL(new Blob([`﻿${[header, ...lines].join("\r\n")}`], { type: "text/csv;charset=utf-8" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = `${slug}-rows-to-fix.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  const ready = rows?.filter((row) => row.outcome === "ready").length ?? 0;
  const skipped = rows?.filter((row) => row.outcome === "skipped").length ?? 0;
  const failed = rows?.filter((row) => row.outcome === "failed").length ?? 0;

  return (
    <>
      <div className="row">
        {registers.map((item) => (
          <button key={item.slug} type="button" className={`toggle${item.slug === slug ? " on" : ""}`} onClick={() => choose(item.slug)}>{item.label}</button>
        ))}
      </div>
      <section className="card">
        <h2>{register.label} template columns</h2>
        <div className="chips">{register.columns.map((column, index) => <span key={column} className="chip">{index + 1}. {column}</span>)}</div>
        <div className="row">
          <a className="btn" href={`/api/templates/${slug}`}>Download template</a>
          <label className="btn primary" style={{ cursor: busy ? "default" : "pointer" }}>
            {busy ? "Checking..." : "Upload filled file"}
            <input className="sr-input" type="file" accept=".xlsx,.csv" onChange={picked} disabled={busy} />
          </label>
          {file && <span className="muted">{file.name}</span>}
        </div>
      </section>
      {error && <p className="error" role="alert">{error}</p>}
      {done && (
        <p className="success" role="status">
          Upload complete: {done.created} created, {done.skipped} skipped, {done.failed} failed. New rows are Pending. <Link href={`/r/${slug}`}>View {register.label}</Link>
        </p>
      )}
      {rows && (
        <section className="card">
          <div className="row between">
            <div>
              <h2>{done ? "Upload result" : "Check the file"}</h2>
              <p className="muted">{done ? `${rows.length} rows processed` : `${ready} ready, ${skipped} skipped, ${failed} failed`}</p>
            </div>
            <div className="row">
              {skipped + failed > 0 && <button type="button" className="btn" onClick={downloadFailed}>Download rows to fix</button>}
              {!done && <button type="button" className="btn primary" disabled={busy || ready === 0 || !file} onClick={() => file && send(file, "confirm")}>Save valid rows</button>}
            </div>
          </div>
          <div className="table-box">
            <table className="data fill">
              <thead>
                <tr><th>Row</th><th>Number</th><th>Date</th><th>Party</th><th className="right">Amount</th><th>Result</th></tr>
              </thead>
              <tbody>
                {rows.slice(0, SHOWN_ROWS).map((row) => (
                  <tr key={row.row}>
                    <td>{row.row}</td><td>{row.no}</td><td>{row.date || "Blank"}</td><td>{row.party}</td><td className="right">{row.amount}</td>
                    <td style={{ fontWeight: 600, color: row.outcome === "ready" ? "#0b5c55" : row.outcome === "skipped" ? "#7a4b00" : "#a32a17" }}>
                      {row.outcome === "ready" ? (done ? "Saved" : "Ready") : `${row.outcome === "skipped" ? "Skipped" : "Failed"}: ${row.reason}`}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {rows.length > SHOWN_ROWS && <p className="muted">Showing the first {SHOWN_ROWS} of {rows.length} rows.</p>}
        </section>
      )}
    </>
  );
}
