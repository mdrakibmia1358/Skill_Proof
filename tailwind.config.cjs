/** @type {import('tailwindcss').Config} */
module.exports = {
  content: {
    relative: true,
    files: [
      './**/*.{php,html,js}',
      '!./node_modules/**',
      '!./auth_system/vendor/**',
      '!./tests/**',
      '!./test-results/**',
      '!./playwright-report/**',
    ],
  },
  // PHP status/score branches and JavaScript validation use complete class names.
  // Keep their finite palette explicit as well; never interpolate bg-${color}-500.
  // Variable score widths stay in inline styles, so arbitrary percentages survive.
  safelist: [
    'bg-green-50', 'bg-green-100', 'bg-green-500', 'text-green-600', 'text-green-700',
    'bg-blue-50', 'bg-blue-500', 'text-blue-800',
    'bg-indigo-50', 'text-indigo-800',
    'bg-slate-100', 'bg-slate-500', 'text-slate-500', 'text-slate-600',
    'bg-emerald-100', 'bg-emerald-500', 'text-emerald-600', 'text-emerald-700',
    'bg-amber-100', 'bg-amber-500', 'text-amber-600', 'text-amber-700',
    'bg-yellow-100', 'bg-yellow-500', 'text-yellow-600', 'text-yellow-700',
    'bg-red-400', 'bg-red-500', 'text-red-500',
    'border-red-500', 'border-green-500', 'focus:ring-red-500', 'focus:ring-green-500',
  ],
  theme: { extend: {} },
  plugins: [],
};
