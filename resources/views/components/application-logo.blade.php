{{--
    The Servora mark, as a single path that takes its colour from the caller.

    Callers set it with `fill-current` plus a text colour, so this deliberately
    carries no fill of its own and no gradient — it is the one-colour mark.
    For the full-colour artwork use public/images/servora-mark.svg, and for a
    lockup with the wordmark use one of the servora-logo-*.png files. See
    docs/09-brand.md.

    Geometry is the same S as scripts/make-brand-assets.php draws. If the mark
    is ever reshaped there, copy the polygon out of the regenerated
    public/images/servora-mark.svg rather than redrawing it here.
--}}
<svg viewBox="0 0 115 100" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Servora" {{ $attributes }}>
    <polygon points="115,0 17.25,0 44.57,66 14.08,66 0,100 97.75,100 70.43,34 100.92,34"/>
</svg>
