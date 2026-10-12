import { forwardRef, useId } from 'react';

const Textarea = forwardRef(function Textarea(
  { id, label, error, hint, required = false, className = '', rows = 3, ...props },
  ref
) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  return (
    <div className={['flex flex-col gap-1.5', className].filter(Boolean).join(' ')}>
      {label && (
        <label htmlFor={inputId} className="text-sm font-medium text-foreground">
          {label}{required && <span className="text-danger"> *</span>}
        </label>
      )}
      <textarea ref={ref} id={inputId} rows={rows} required={required} className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted transition-colors duration-150 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50" {...props} />
      {hint && !error && <p className="text-xs text-muted">{hint}</p>}
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  );
});

export default Textarea;