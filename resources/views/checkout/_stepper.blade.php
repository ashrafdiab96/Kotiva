@php
    /*
     | Review → Shipping → Payment. Steps already passed are links so the
     | shopper can go back and correct something; the current step and steps
     | ahead are not, because a step reached out of order is redirected back
     | anyway and a dead link is worse than no link.
     */
    $steps = [
        'review' => ['label' => 'Review', 'route' => 'checkout.review'],
        'shipping' => ['label' => 'Shipping', 'route' => 'checkout.shipping'],
        'payment' => ['label' => 'Payment', 'route' => 'checkout.payment'],
    ];
    $order = array_keys($steps);
    $currentIndex = array_search($step, $order, true);
@endphp

<nav class="checkout-steps" aria-label="Checkout progress">
  <ol class="checkout-steps-list">
    @foreach ($steps as $key => $meta)
      @php
        $index = array_search($key, $order, true);
        $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'todo');
      @endphp
      <li class="checkout-step is-{{ $state }}">
        @if ($state === 'done')
          <a class="checkout-step-link" href="{{ route($meta['route']) }}">
            <span class="checkout-step-num">{{ $index + 1 }}</span>
            <span class="checkout-step-label">{{ $meta['label'] }}</span>
          </a>
        @else
          <span class="checkout-step-link" @if ($state === 'current') aria-current="step" @endif>
            <span class="checkout-step-num">{{ $index + 1 }}</span>
            <span class="checkout-step-label">{{ $meta['label'] }}</span>
          </span>
        @endif
      </li>
    @endforeach
  </ol>
</nav>
