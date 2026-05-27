---
name: react-frontend-development
description: "Build React.js and Next.js frontend applications with TypeScript, Tailwind CSS, and Shadcn UI. Use when creating components, hooks, pages, API integration, state management, forms, routing, or responsive UI. Triggers: React, Next.js, TypeScript, frontend, component, Tailwind, Shadcn."
---
# React Frontend Development

## When to Use
- Creating React/Next.js components and pages
- Building forms, tables, dashboards
- Integrating with Laravel/Node.js APIs
- State management, routing, authentication flows
- Responsive, accessible, performant UI/UX

## Architecture

```
src/
├── app/                    # Next.js App Router pages
│   ├── (auth)/             # Auth route group
│   ├── (dashboard)/        # Dashboard route group
│   └── layout.tsx
├── components/
│   ├── ui/                 # Shadcn UI primitives
│   └── features/           # Feature-specific composites
├── hooks/                  # Custom hooks
├── lib/                    # Utilities, API client, constants
├── services/               # API service layer
├── stores/                 # State management (Zustand/Context)
└── types/                  # TypeScript type definitions
```

## Component Patterns

### Functional Components Only
```tsx
interface FeatureCardProps {
  title: string;
  description: string;
  onAction: (id: string) => void;
}

export function FeatureCard({ title, description, onAction }: FeatureCardProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
    </Card>
  );
}
```

### Custom Hooks for Logic
```tsx
export function useFeatures() {
  const [features, setFeatures] = useState<Feature[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const fetchFeatures = useCallback(async () => {
    setIsLoading(true);
    try {
      const data = await featureService.getAll();
      setFeatures(data);
    } finally {
      setIsLoading(false);
    }
  }, []);

  return { features, isLoading, fetchFeatures };
}
```

### API Service Layer
```tsx
class FeatureService {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: process.env.NEXT_PUBLIC_API_URL,
      headers: { "Content-Type": "application/json" },
    });
  }

  async getAll(): Promise<Feature[]> {
    const { data } = await this.client.get<ApiResponse<Feature[]>>("/features");
    return data.data;
  }
}

export const featureService = new FeatureService();
```

## Styling Rules
- Tailwind CSS utility-first — no custom CSS unless necessary
- Shadcn UI for base components (Button, Card, Dialog, Table, Form)
- `cn()` helper for conditional classes
- Mobile-first responsive: `sm:`, `md:`, `lg:` breakpoints
- Dark mode support via `dark:` variants

## TypeScript Rules
- Strict mode enabled — no `any`
- Interfaces for component props, API responses
- Discriminated unions for state variants
- Zod for runtime validation (forms, API responses)
- Enum alternatives: `as const` objects

## Form Handling
- React Hook Form + Zod for validation
- Shadcn Form components for consistent UI
- Server-side validation errors displayed inline
- Optimistic updates where appropriate

## State Management
- Local state: `useState`, `useReducer`
- Shared state: Zustand or React Context
- Server state: TanStack Query (React Query)
- URL state: `useSearchParams` for filters/pagination

## Performance
- Dynamic imports: `next/dynamic` for heavy components
- Image optimization: `next/image`
- Memoization: `useMemo`, `useCallback` for expensive ops
- Virtualization for long lists (TanStack Virtual)
- Suspense boundaries for loading states

## Security
- Never expose secrets in client code
- Sanitize user input before rendering
- CSRF protection for form submissions
- Secure cookie-based auth for SPAs
- Content Security Policy headers
