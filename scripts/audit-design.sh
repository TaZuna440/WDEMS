#!/usr/bin/env bash
set -euo pipefail

VIOLATIONS=0

echo "════════════════════════════════════════════════════"
echo " WDEMS design system audit"
echo "════════════════════════════════════════════════════"

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

echo ""
echo "▸ Rule 2: .text-on-image only on page hero headings"
echo ""

RULE2=$(grep -rn "text-on-image" resources/js/ --include="*.tsx" \
  | grep -v "pages/dashboard.tsx" \
  | grep -v "pages/events/index.tsx" \
  || true)

if [ -n "$RULE2" ]; then
  echo "  ⚠ text-on-image found outside known page heroes:"
  echo "$RULE2" | sed 's/^/    /'
else
  echo "  ✓ text-on-image usage is limited to page heroes."
fi

echo ""
echo "▸ Rule 3: glass tint variables only in app.css"
echo ""

RULE3=$(grep -rn -- "--glass-card-tint\|--glass-sidebar-tint\|--glass-header-tint" \
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

echo ""
echo "════════════════════════════════════════════════════"
if [ "$VIOLATIONS" -gt 0 ]; then
  echo " RESULT: $VIOLATIONS hard violation(s). Fix before commit."
  echo "════════════════════════════════════════════════════"
  exit 1
fi

echo " RESULT: Clean. Design system rules are satisfied."
echo "════════════════════════════════════════════════════"
