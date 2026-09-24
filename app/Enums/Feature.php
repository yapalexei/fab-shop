<?php

namespace App\Enums;

enum Feature: string
{
    case Customers = 'customers';
    case QuoteBuilder = 'quote-builder';
    case JobBoard = 'job-board';
    case QuickbooksSync = 'quickbooks-sync';
    case MaterialTracking = 'material-tracking';
    case WeldRecords = 'weld-records';
    case MobileApi = 'mobile-api';
}
