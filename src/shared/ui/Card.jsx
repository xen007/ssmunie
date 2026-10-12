/**
 * Carte SSMUNIE : surface + bordure + radius md + ombre sm.
 */
export default function Card({ className = '', children, ...props }) {
  const classes = ['rounded-md border border-border bg-surface shadow-sm', className]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={classes} {...props}>
      {children}
    </div>
  );
}
