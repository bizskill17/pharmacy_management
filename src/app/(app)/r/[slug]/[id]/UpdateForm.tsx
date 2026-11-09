"use client";

import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { saveRemarks } from "@/app/actions/documents";
import type { FormSection, Kind, PhotoGroup } from "@/lib/registers";
import { slotLabel } from "@/lib/registers";

interface Photo {
  id: number;
  position: number;
}

interface Props {
  documentId: number;
  title: string;
  groups: PhotoGroup[];
  sections: FormSection[];
  initialPhotos: Record<string, Photo[]>;
  uploadDate: string;
  employee: string;
  initialRemarks: string;
  locked: boolean;
}

const MAX_SIDE = 1600;

/** Shrinks photos on the phone so uploads are quick. Falls back to the original file. */
async function compress(file: File): Promise<Blob> {
  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext("2d");
    if (!context) return file;
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.82));
    return blob ?? file;
  } catch {
    return file;
  }
}

function OpenSlot({ kind, label, busy, onFile }: { kind: Kind; label: string; busy: boolean; onFile: (file: File) => void }) {
  const uploadRef = useRef<HTMLInputElement>(null);
  const cameraRef = useRef<HTMLInputElement>(null);
  const picked = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (file) onFile(file);
  };
  return (
    <div className="slot">
      <div className="slot-name">{label}</div>
      <input ref={uploadRef} className="sr-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={picked} tabIndex={-1} aria-label={`Upload ${label}`} data-kind={kind} />
      <input ref={cameraRef} className="sr-input" type="file" accept="image/*" capture="environment" onChange={picked} tabIndex={-1} aria-label={`Click ${label}`} />
      <button type="button" className="btn" disabled={busy} onClick={() => uploadRef.current?.click()}>{busy ? "Saving..." : "Upload photo"}</button>
      <button type="button" className="btn primary" disabled={busy} onClick={() => cameraRef.current?.click()}>Click photo</button>
    </div>
  );
}

export function UpdateForm(props: Props) {
  const router = useRouter();
  const [photos, setPhotos] = useState(props.initialPhotos);
  const [busyKind, setBusyKind] = useState<Kind | null>(null);
  const [remarks, setRemarks] = useState(props.initialRemarks);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [saving, setSaving] = useState(false);

  async function add(group: PhotoGroup, file: File) {
    setBusyKind(group.kind);
    setMessage(null);
    try {
      const blob = await compress(file);
      const form = new FormData();
      form.set("kind", group.kind);
      form.set("file", blob, file.name || "photo.jpg");
      const response = await fetch(`/api/documents/${props.documentId}/attachments`, { method: "POST", body: form });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error ?? "Photo could not be saved.");
      setPhotos((current) => ({ ...current, [group.kind]: [...(current[group.kind] ?? []), { id: result.id, position: result.position }] }));
      router.refresh();
    } catch (error) {
      setMessage({ ok: false, text: error instanceof Error ? error.message : "Photo could not be saved." });
    } finally {
      setBusyKind(null);
    }
  }

  async function remove(group: PhotoGroup, photo: Photo) {
    setBusyKind(group.kind);
    setMessage(null);
    try {
      const response = await fetch(`/api/documents/${props.documentId}/attachments/${photo.id}`, { method: "DELETE" });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error ?? "Photo could not be removed.");
      setPhotos((current) => ({ ...current, [group.kind]: (current[group.kind] ?? []).filter((item) => item.id !== photo.id) }));
      router.refresh();
    } catch (error) {
      setMessage({ ok: false, text: error instanceof Error ? error.message : "Photo could not be removed." });
    } finally {
      setBusyKind(null);
    }
  }

  async function save() {
    setSaving(true);
    setMessage(null);
    const hasRemarks = props.sections.some((section) => section.type === "remarks");
    const result = hasRemarks ? await saveRemarks(props.documentId, remarks) : { ok: true, message: "Update saved." };
    setMessage({ ok: result.ok, text: result.message });
    setSaving(false);
    router.refresh();
  }

  return (
    <section className="card form" aria-label={props.title}>
      <div>
        <h2>{props.title}</h2>
        <p className="muted">The next photo opens after the one before it is added. Photos save as soon as they are added.</p>
      </div>
      {message && <p className={message.ok ? "success" : "error"} role="status">{message.text}</p>}
      {props.sections.map((section) => {
        if (section.type === "photos") {
          const group = props.groups.find((item) => item.kind === section.kind) as PhotoGroup;
          const items = photos[group.kind] ?? [];
          return (
            <div key={group.kind} className="field">
              <div className="row between">
                <h3>{group.max === 1 ? group.label : `${group.label}s`}</h3>
                <span className="muted">{group.required ? "Required" : "Optional"} &middot; {items.length} of {group.max} added</span>
              </div>
              <div className="slots">
                {items.map((photo, index) => (
                  <div key={photo.id} className="slot">
                    <div className="slot-name">{slotLabel(group.label, index + 1)}</div>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={`/api/files/${photo.id}`} alt={`${slotLabel(group.label, index + 1)} preview`} />
                    {index === items.length - 1 && !props.locked && (
                      <button type="button" className="btn" disabled={busyKind === group.kind} onClick={() => remove(group, photo)}>Remove</button>
                    )}
                  </div>
                ))}
                {items.length < group.max && !props.locked && (
                  <OpenSlot kind={group.kind} label={slotLabel(group.label, items.length + 1)} busy={busyKind === group.kind} onFile={(file) => add(group, file)} />
                )}
              </div>
            </div>
          );
        }
        if (section.type === "auto") {
          return (
            <div key="auto" className="grid-auto">
              <div className="field">
                <label htmlFor="upload-date">Upload Date</label>
                <input id="upload-date" className="input" readOnly value={props.uploadDate} />
              </div>
              <div className="field">
                <label htmlFor="uploaded-by">Uploaded By (Employee)</label>
                <input id="uploaded-by" className="input" readOnly value={props.employee} />
              </div>
            </div>
          );
        }
        return (
          <div key="remarks" className="field">
            <label htmlFor="remarks">Remarks</label>
            <textarea id="remarks" className="input" maxLength={2000} value={remarks} onChange={(event) => setRemarks(event.target.value)} disabled={props.locked} />
          </div>
        );
      })}
      {!props.locked && (
        <div className="row"><button type="button" className="btn primary" onClick={save} disabled={saving}>{saving ? "Saving..." : "Save update"}</button></div>
      )}
    </section>
  );
}
