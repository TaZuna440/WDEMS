#!/usr/bin/env bash
set -euo pipefail

VIOLATIONS=0
WARNINGS=0

echo "════════════════════════════════════════════════════"
echo " WDEMS design system audit"
echo "════════════════════════════════════════════════════"

# ─── Rule 1: yellow / amber must have a light-mode override ───
echo ""
echo "▸ Rule 1: yellow / amber must have a light-mode override"
echo ""
RULE1=$(grep -rn \
  "bg-yellow-500\|text-yellow-500\|border-yellow-500\|bg-amber-500\|text-amber-400\|text-amber-300\|text-amber-200\|border-amber-500\|border-l-amber-500" \
  resources/js/ \
  --include="*.tsx" \
  | grep -v "components/ui/" \
  | grep -v "dark:" \
  || true)

if [ -n "$RULE1" ]; then
  echo "  ✗ Violations found:"
  echo "$RULE1" | sed 's/^/    /'
  VIOLATIONS=$((VIOLATIONS + 1))
else
  echo "  ✓ All yellow / amber usages are paired with a dark: variant."
fi

# ─── Rule 2: page heroes must use text-on-image, not text-foreground ───
echo ""
echo "▸ Rule 2: page heroes use text-on-image on the app layout"
echo ""
echo "  Whitelisted (correct as-is): auth/*, welcome, public"
echo "  registration pages, settings sub-pages, admin, and the two"
echo "  discipleship h1s that sit inside a glass-panel."
echo ""
RULE2=$(grep -rn '<h1 className="[^"]*text-foreground' resources/js/pages/ \
  | grep -v "pages/auth/" \
  | grep -v "pages/welcome.tsx" \
  | grep -v "pages/registrations/public.tsx" \
  | grep -v "pages/registrations/confirmed.tsx" \
  | grep -v "pages/registrations/closed.tsx" \
  | grep -v "pages/registrations/verify.tsx" \
  | grep -v "pages/settings/" \
  | grep -v "pages/admin/" \
  | grep -v "pages/discipleship/batch-show.tsx" \
  | grep -v "pages/discipleship/show.tsx" \
  | grep -v "pages/fellowship/attendance.tsx" \
  || true)

if [ -n "$RULE2" ]; then
  echo "  ✗ Hero h1 still using text-foreground on the app layout:"
  echo "$RULE2" | sed 's/^/    /'
  VIOLATIONS=$((VIOLATIONS + 1))
else
  echo "  ✓ All page heroes use text-on-image (or are whitelisted)."
fi

# ─── Rule 3: glass tint variables only in app.css ───
echo ""
echo "▸ Rule 3: glass tint variables only in app.css"
echo ""
RULE3=$(grep -rn "glass-card-tint\|glass-sidebar-tint\|glass-header-tint" \
  resources/ \
  --include="*.tsx" \
  --include="*.js" \
  --include="*.css" \
  | grep -v "resources/css/app.css" \
  || true)

if [ -n "$RULE3" ]; then
  echo "  ✗ Glass tint variables found outside app.css:"
  echo "$RULE3" | sed 's/^/    /'
  VIOLATIONS=$((VIOLATIONS + 1))
else
  echo "  ✓ Glass tint variables are centralized in app.css."
fi

# ─── Rule 4: text-*-400 must carry a dark: pair ───
echo ""
echo "▸ Rule 4: bright status colors must carry a dark: pair"
echo "  Fix pattern: text-green-700 dark:text-green-400"
echo ""
RULE4=$(grep -rn \
  "text-green-400\|text-blue-400\|text-red-400\|text-amber-400\|text-yellow-400\|text-teal-400" \
  resources/js/pages/ \
  resources/js/components/ \
  --include="*.tsx" \
  | grep -v "dark:" \
  | grep -v "components/ui/" \
  || true)

if [ -n "$RULE4" ]; then
  echo "  ✗ Violations found:"
  echo "$RULE4" | sed 's/^/    /'
  VIOLATIONS=$((VIOLATIONS + 1))
else
  echo "  ✓ All bright status colors are paired with a light-mode value."
fi

# ─── Rule 5: raw white utilities in pages / layouts (informational) ───
echo ""
echo "▸ Rule 5: raw white utilities in pages / layouts (informational)"
echo "  In-glass usage is fine. On-image usage is a bug. Review each file."
echo ""
RULE5=$(grep -rln "border-white/\|bg-white/" \
  resources/js/pages/ \
  resources/js/layouts/ \
  --include="*.tsx" \
  | sort \
  || true)

if [ -n "$RULE5" ]; then
  echo "  ⚠ Files with raw white utilities:"
  echo "$RULE5" | sed 's/^/    /'
  WARNINGS=$((WARNINGS + 1))
else
  echo "  ✓ No hardcoded white utilities in pages/ or layouts/."
fi

# ─── Rule 6: raw anchor buttons must carry a surface ───
echo ""
echo "▸ Rule 6: raw anchor buttons must carry a card surface"
echo "  Pattern: inline-flex h-8 ... border ... bg-card/80 ... backdrop-blur-sm"
echo ""
RULE6=$(grep -rn 'inline-flex h-8 items-center rounded-md border' \
  resources/js/pages/ \
  --include="*.tsx" \
  | grep -v 'bg-card/80' \
  || true)

if [ -n "$RULE6" ]; then
  echo "  ✗ Anchor buttons missing surface treatment:"
  echo "$RULE6" | sed 's/^/    /'
  VIOLATIONS=$((VIOLATIONS + 1))
else
  echo "  ✓ All raw anchor buttons carry a card surface."
fi

# ─── Result ───
echo ""
echo "════════════════════════════════════════════════════"
if [ "$VIOLATIONS" -gt 0 ]; then
  echo " RESULT: $VIOLATIONS hard violation(s). Fix before commit."
  echo "════════════════════════════════════════════════════"
  exit 1
fi
if [ "$WARNINGS" -gt 0 ]; then
  echo " RESULT: Clean on hard rules. $WARNINGS informational warning(s)."
  echo "════════════════════════════════════════════════════"
  exit 0
fi
echo " RESULT: Clean."
echo "════════════════════════════════════════════════════"
