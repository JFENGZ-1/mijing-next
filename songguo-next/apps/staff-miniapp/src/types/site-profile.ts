export interface SiteRegion {
  provinceCode?: string | null;
  provinceName?: string | null;
  cityCode?: string | null;
  cityName?: string | null;
  countyCode?: string | null;
  countyName?: string | null;
}

export interface SiteBusinessHour {
  weekDays: string;
  timeValue: string;
}

export interface SiteProfile {
  id: number;
  name: string;
  phone?: string | null;
  address?: string | null;
  logoUrl?: string | null;
  logoUrlWeb?: string | null;
  description?: string | null;
  region?: SiteRegion | null;
  businessHours: SiteBusinessHour[];
  longitude?: number | null;
  latitude?: number | null;
  timezone: string;
  version: number;
}

export interface SiteProfileUpdatePayload {
  name?: string;
  phone?: string | null;
  address?: string | null;
  logoUrl?: string | null;
  description?: string | null;
  region?: SiteRegion | null;
  businessHours?: SiteBusinessHour[];
  version: number;
}

export interface SiteRegionConstants {
  province: Array<{ citiesProvinceCode: string; citiesProvinceName: string }>;
  city: Array<{ citiesCityCode: string; citiesCityName: string; citiesProvinceCode: string }>;
  county: Array<{ citiesCountyCode: string; citiesCountyName: string; citiesCityCode: string }>;
}
