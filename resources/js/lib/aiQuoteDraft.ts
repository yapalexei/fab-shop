import type {
    EquipmentOption,
    LineItem,
    MarkupDefaults,
    MaterialOption,
    ServiceOption,
    ToolOption,
} from '@/Components/QuoteForm';

/** One suggested line as the server returns it - no prices, ever. */
export interface AiSuggestedLine {
    type: LineItem['type'];
    description: string;
    quantity: number;
    /** An id in the list that matches `type` (materials, services, ...), or null. */
    catalog_id: number | null;
    confidence: number;
    /** Why the owner should double-check this line, if they should. */
    note: string | null;
}

export interface AiDraftResult {
    lines: AiSuggestedLine[];
    questions: string[];
    catalog_truncated: boolean;
}

interface Catalog {
    materials: MaterialOption[];
    services: ServiceOption[];
    equipmentList: EquipmentOption[];
    tools: ToolOption[];
}

const cheapest = <T extends { price: number }>(partners: T[]): T | undefined =>
    partners.length === 0
        ? undefined
        : partners.reduce((best, p) => (p.price < best.price ? p : best));

/**
 * A quote line from a suggestion. The AI only chose *what* (and how many);
 * cost and markup come from the shop's own catalog the same way they do when
 * the owner picks the item by hand in the line card - the cheapest known
 * supplier price, the equipment rate, the tool cost - so a model can never
 * put a number on a quote. A line with no catalog match starts at $0 and is
 * flagged, for the owner to price.
 */
export function lineItemFromSuggestion(
    suggestion: AiSuggestedLine,
    catalog: Catalog,
    markupDefaults: MarkupDefaults,
): LineItem {
    const item: LineItem = {
        type: suggestion.type,
        description: suggestion.description,
        quantity: suggestion.quantity,
        unit_cost: 0,
        markup_percent: markupDefaults[suggestion.type],
        override_total: null,
        material_id: null,
        service_id: null,
        business_partner_id: null,
        material_dimension: null,
        user_id: null,
        equipment_id: null,
        tool_id: null,
        ai_note: suggestion.note,
    };

    const id = suggestion.catalog_id;
    if (id === null) return item;

    if (suggestion.type === 'material') {
        const material = catalog.materials.find((m) => m.id === id);
        if (!material) return item;

        const partner = cheapest(material.partners);
        return {
            ...item,
            material_id: material.id,
            business_partner_id: partner?.business_partner_id ?? null,
            material_dimension: partner?.dimension ?? null,
            unit_cost: partner?.price ?? 0,
        };
    }

    if (suggestion.type === 'labor') {
        const service = catalog.services.find((s) => s.id === id);
        const partner = service && cheapest(service.partners);

        // A labor line is only linked when a supplier prices it - the line
        // card needs both the service and the partner.
        return service && partner
            ? {
                  ...item,
                  service_id: service.id,
                  business_partner_id: partner.business_partner_id,
                  unit_cost: partner.price,
              }
            : item;
    }

    if (suggestion.type === 'equipment') {
        const equipment = catalog.equipmentList.find((e) => e.id === id);
        return equipment
            ? { ...item, equipment_id: equipment.id, unit_cost: equipment.rate }
            : item;
    }

    const tool = catalog.tools.find((t) => t.id === id);
    return tool ? { ...item, tool_id: tool.id, unit_cost: tool.cost } : item;
}
