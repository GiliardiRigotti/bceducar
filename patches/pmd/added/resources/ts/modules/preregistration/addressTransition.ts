import { Address } from '@/types/types';

export const hasConfirmedCoordinates = (address?: Partial<Address>) => {
  const lat = address?.lat;
  const lng = address?.lng;
  return typeof lat === 'number' && typeof lng === 'number' &&
    Number.isFinite(lat) && Number.isFinite(lng) &&
    Math.abs(lat) <= 90 && Math.abs(lng) <= 180 &&
    (lat !== 0 || lng !== 0);
};

// The form contains visible fields; retain municipality and location metadata.
export const mergeAddressFields = (current: Address, submitted: Partial<Address>): Address => ({
  ...current,
  ...submitted,
});
