import {IConfigService,DEFAULT_WORKSHEET_ROW_COUNT_KEY,DEFAULT_WORKSHEET_COLUMN_COUNT_KEY} from '@univerjs/core';
import { createUniver, LocaleType, mergeLocales } from '@univerjs/presets';
import { UniverSheetsCorePreset } from '@univerjs/preset-sheets-core';
import CoreVi from '@univerjs/preset-sheets-core/locales/vi-VN';
import '@univerjs/preset-sheets-core/lib/index.css';
import { UniverSheetsFilterPreset } from '@univerjs/preset-sheets-filter';
import FilterVi from '@univerjs/preset-sheets-filter/locales/vi-VN';
import '@univerjs/preset-sheets-filter/lib/index.css';
import { UniverSheetsSortPreset } from '@univerjs/preset-sheets-sort';
import SortVi from '@univerjs/preset-sheets-sort/locales/vi-VN';
import '@univerjs/preset-sheets-sort/lib/index.css';
import { UniverSheetsFindReplacePreset } from '@univerjs/preset-sheets-find-replace';
import FindReplaceVi from '@univerjs/preset-sheets-find-replace/locales/vi-VN';
import '@univerjs/preset-sheets-find-replace/lib/index.css';
import { UniverSheetsDataValidationPreset } from '@univerjs/preset-sheets-data-validation';
import DataValidationVi from '@univerjs/preset-sheets-data-validation/locales/vi-VN';
import '@univerjs/preset-sheets-data-validation/lib/index.css';
import { UniverSheetsConditionalFormattingPreset } from '@univerjs/preset-sheets-conditional-formatting';
import ConditionalFormattingVi from '@univerjs/preset-sheets-conditional-formatting/locales/vi-VN';
import '@univerjs/preset-sheets-conditional-formatting/lib/index.css';
export function createEditor(snapshot){const {univer,univerAPI}=createUniver({locale:LocaleType.VI_VN,locales:{[LocaleType.VI_VN]:mergeLocales(CoreVi,FilterVi,SortVi,FindReplaceVi,DataValidationVi,ConditionalFormattingVi)},presets:[UniverSheetsCorePreset({container:'editor',header:true,toolbar:true,formulaBar:true,footer:true,sheets:{allowArrowKeyWrapOnSheetBoundary:false}}),UniverSheetsFilterPreset(),UniverSheetsSortPreset(),UniverSheetsFindReplacePreset(),UniverSheetsDataValidationPreset(),UniverSheetsConditionalFormattingPreset()]});const config=univer.__getInjector().get(IConfigService);config.setConfig(DEFAULT_WORKSHEET_ROW_COUNT_KEY,200);config.setConfig(DEFAULT_WORKSHEET_COLUMN_COUNT_KEY,17);const workbook=univerAPI.createWorkbook(snapshot);return {api:univerAPI,workbook,univer};}

