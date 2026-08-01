import { render, screen } from '@testing-library/react';

import { Button } from '@/components/ui/button';

describe('Button', () => {
  it('renders its children and applies the default variant classes', () => {
    render(<Button>Click me</Button>);

    const button = screen.getByRole('button', { name: 'Click me' });

    expect(button).toBeInTheDocument();
    expect(button).toHaveClass('bg-primary');
  });

  it('renders as child when asChild is set', () => {
    render(
      <Button asChild>
        <a href="/login">Login</a>
      </Button>,
    );

    const link = screen.getByRole('link', { name: 'Login' });

    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/login');
  });
});
