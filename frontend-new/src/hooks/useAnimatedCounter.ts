import { useEffect, useState, useRef } from 'react';

interface UseAnimatedCounterOptions {
  target: number;
  duration?: number;
  enabled?: boolean;
}

export function useAnimatedCounter({ 
  target, 
  duration = 1500,
  enabled = true 
}: UseAnimatedCounterOptions): number {
  const [count, setCount] = useState(0);
  const animationFrameRef = useRef<number | null>(null);
  const startTimeRef = useRef<number | null>(null);
  const previousTargetRef = useRef<number>(0);

  useEffect(() => {
    if (!enabled) {
      setCount(target);
      return;
    }

    // Если значение не изменилось, не запускаем анимацию
    if (target === previousTargetRef.current) {
      return;
    }

    const startValue = previousTargetRef.current;
    const endValue = target;
    const difference = endValue - startValue;

    // Если разница слишком мала, сразу устанавливаем значение
    if (Math.abs(difference) < 0.01) {
      setCount(target);
      previousTargetRef.current = target;
      return;
    }

    // Очищаем предыдущую анимацию
    if (animationFrameRef.current) {
      cancelAnimationFrame(animationFrameRef.current);
    }

    startTimeRef.current = null;

    const animate = (currentTime: number) => {
      if (startTimeRef.current === null) {
        startTimeRef.current = currentTime;
      }

      const elapsed = currentTime - startTimeRef.current;
      const progress = Math.min(elapsed / duration, 1);

      // Используем easing функцию для плавной анимации
      const easeOutCubic = 1 - Math.pow(1 - progress, 3);
      const currentValue = startValue + difference * easeOutCubic;

      setCount(currentValue);

      if (progress < 1) {
        animationFrameRef.current = requestAnimationFrame(animate);
      } else {
        setCount(endValue);
        previousTargetRef.current = endValue;
        animationFrameRef.current = null;
      }
    };

    animationFrameRef.current = requestAnimationFrame(animate);

    return () => {
      if (animationFrameRef.current) {
        cancelAnimationFrame(animationFrameRef.current);
      }
    };
  }, [target, duration, enabled]);

  return count;
}

