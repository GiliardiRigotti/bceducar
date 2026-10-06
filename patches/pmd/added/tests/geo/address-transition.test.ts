import { describe, expect, it } from 'vitest';
import { hasConfirmedCoordinates, mergeAddressFields } from '@/modules/preregistration/addressTransition';
import { Address } from '@/types/types';
describe('address transition', () => {
  it('recognizes optional valid geographic metadata', () => {
    for (const address of [undefined, {}, {lat:0,lng:0}, {lat:NaN,lng:1}, {lat:91,lng:1}, {lat:1,lng:-181}]) {
      expect(hasConfirmedCoordinates(address)).toBe(false);
    }
    expect(hasConfirmedCoordinates({lat:-26.99,lng:-48.63})).toBe(true);
    expect(hasConfirmedCoordinates({lat:0,lng:-48})).toBe(true);
  });
  it('preserves municipality metadata and uses submitted coordinates', () => {
    const current = {cityIbgeCode:4202008, manualChangeLocation:true, address:'Old',lat:0,lng:0} as Address;
    const submitted = {address:'New',lat:-26.99,lng:-48.63};
    expect(mergeAddressFields(current,submitted)).toEqual({...current,...submitted});
    expect(current.address).toBe('Old');
  });
});
