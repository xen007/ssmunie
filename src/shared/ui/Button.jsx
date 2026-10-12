import { forwardRef } from 'react';

const base =
  'inline-flex items-center justify-center gap-2 rounded-md font-medium ' +
  'transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 ' +
  'focus-visible:ring-primary/50 focus-visible:ring-offset-2 ' +
  'focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50';

const variants = {
  primary: 'bg-primary text-white hover:bg-primary-hover',
  secondary:
    'border border-border bg-surface text-foreground hover:bg-primary-soft hover:text-primary',
  ghost: 'bg-transparent text-foreground hover:bg-primary-soft hover:text-primary',
  danger: 'bg-danger text-white hover:opacity-90',
};

const sizes = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-12 px-6 text-base',
};

/**
 * Bouton SSMUNIE.
 * Variantes : primary | secondary | ghost | danger
 * Tailles   : sm | md | lg
 * Icône optionnelle (lucide-react) via la prop `icon` / `iconRight`.
 */
const Button = forwardRef(function Button(
  {
    variant = 'primary',
    size = 'md',
    icon: Icon,
    iconRight: IconRight,
    className = '',
    type = 'button',
    disabled = false,
    children,
    ...props
  },
  ref
) {
  const classes = [base, variants[variant] ?? variants.primary, sizes[size] ?? sizes.md, className]
    .filter(Boolean)
    .join(' ');

  return (
    <button ref={ref} type={type} disabled={disabled} className={classes} {...props}>
      {Icon && <Icon className="h-4 w-4" aria-hidden="true" />}
      {children}
      {IconRight && <IconRight className="h-4 w-4" aria-hidden="true" />}
    </button>
  );
});

export default Button;
