import { Link } from '@inertiajs/react';
import InputLabel from '@/Components/InputLabel';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import {
    EmployeeOption,
    EquipmentOption,
    LineItem,
    MarkupDefaults,
    MaterialOption,
    MaterialTypeRef,
    ServiceOption,
    ToolOption,
} from '@/Components/QuoteForm';
import { useState } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Alert } from '@/Components/ui/alert';

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

export function lineTotal(item: LineItem): number {
    const quantity = Number(item.quantity) || 0;
    const unitCost = Number(item.unit_cost) || 0;
    const markup = Number(item.markup_percent) || 0;
    const computed = quantity * unitCost * (1 + markup / 100);

    return item.override_total !== null && item.override_total !== ''
        ? Number(item.override_total)
        : computed;
}

export default function QuoteLineItemCard({
    item,
    index,
    materials,
    services,
    equipmentList,
    tools,
    quoteMaterialTypes,
    employees,
    markupDefaults,
    onChange,
    onRemove,
    getError,
    readOnly = false,
}: {
    item: LineItem;
    index: number;
    materials: MaterialOption[];
    services: ServiceOption[];
    equipmentList: EquipmentOption[];
    tools: ToolOption[];
    quoteMaterialTypes: MaterialTypeRef[];
    employees: EmployeeOption[];
    markupDefaults: MarkupDefaults;
    onChange: (index: number, patch: Partial<LineItem>) => void;
    onRemove: (index: number) => void;
    getError: (field: keyof LineItem) => string | undefined;
    /** The whole form is view-only - never show the edit form, just the row. */
    readOnly?: boolean;
}) {
    // A brand-new item (added via "Add line item") has no description yet,
    // so it opens straight into edit mode; anything loaded with one already
    // set (an existing quote, or an item you've already filled in) starts
    // collapsed to the compact row. Only read on mount - typing a
    // description afterwards doesn't re-collapse an item you're editing.
    const [isEditing, setIsEditing] = useState(
        () => item.description.trim() === '',
    );

    const errorClass = (field: keyof LineItem) =>
        getError(field) ? 'border-red-400' : '';

    const changeType = (type: LineItem['type']) => {
        onChange(index, {
            type,
            markup_percent: markupDefaults[type],
            material_id: null,
            service_id: null,
            business_partner_id: null,
            material_dimension: null,
            user_id: null,
            equipment_id: null,
            tool_id: null,
        });
    };

    const selectMaterial = (materialId: string) => {
        if (materialId === '') {
            onChange(index, {
                material_id: null,
                business_partner_id: null,
                material_dimension: null,
            });
            return;
        }
        const material = materials.find((m) => m.id === Number(materialId));
        onChange(index, {
            material_id: Number(materialId),
            business_partner_id: null,
            material_dimension: null,
            description: material?.name ?? item.description,
        });
    };

    // A supplier can price the same material at several sizes, so an option
    // is a supplier + size pair, keyed "partnerId|size".
    const partnerKey = (partnerId: number, dimension: string | null) =>
        `${partnerId}|${dimension ?? ''}`;

    const selectMaterialPartner = (key: string) => {
        const material = materials.find(
            (m) => m.id === Number(item.material_id),
        );
        const partner = material?.partners.find(
            (p) => partnerKey(p.business_partner_id, p.dimension) === key,
        );

        onChange(index, {
            business_partner_id: partner ? partner.business_partner_id : null,
            material_dimension: partner?.dimension ?? null,
            unit_cost: partner ? partner.price : item.unit_cost,
        });
    };

    const laborSourceValue = (): string => {
        if (item.user_id) return `emp:${item.user_id}`;
        if (item.service_id && item.business_partner_id) {
            return `svc:${item.service_id}:${item.business_partner_id}`;
        }
        return '';
    };

    const selectLaborSource = (value: string) => {
        if (value === '') {
            onChange(index, {
                user_id: null,
                service_id: null,
                business_partner_id: null,
                material_dimension: null,
            });
            return;
        }

        if (value.startsWith('emp:')) {
            const userId = Number(value.split(':')[1]);
            const employee = employees.find((e) => e.user_id === userId);
            onChange(index, {
                user_id: userId,
                service_id: null,
                business_partner_id: null,
                material_dimension: null,
                unit_cost: employee?.hourly_rate ?? item.unit_cost,
            });
            return;
        }

        const [, serviceIdRaw, partnerIdRaw] = value.split(':');
        const serviceId = Number(serviceIdRaw);
        const partnerId = Number(partnerIdRaw);
        const service = services.find((s) => s.id === serviceId);
        const partner = service?.partners.find(
            (p) => p.business_partner_id === partnerId,
        );

        onChange(index, {
            user_id: null,
            service_id: serviceId,
            business_partner_id: partnerId,
            description: service?.name ?? item.description,
            unit_cost: partner?.price ?? item.unit_cost,
        });
    };

    const selectEquipment = (equipmentId: string) => {
        if (equipmentId === '') {
            onChange(index, { equipment_id: null });
            return;
        }
        const equipment = equipmentList.find(
            (e) => e.id === Number(equipmentId),
        );
        onChange(index, {
            equipment_id: Number(equipmentId),
            description: equipment?.name ?? item.description,
            unit_cost: equipment?.rate ?? item.unit_cost,
        });
    };

    const selectTool = (toolId: string) => {
        if (toolId === '') {
            onChange(index, { tool_id: null });
            return;
        }
        const tool = tools.find((t) => t.id === Number(toolId));
        onChange(index, {
            tool_id: Number(toolId),
            description: tool
                ? `${tool.name} (${tool.cuts})`
                : item.description,
            unit_cost: tool?.cost ?? item.unit_cost,
        });
    };

    // Advisory only: a tool rated for none of the quote's material types is
    // probably the wrong one (an aluminum bit on a steel job). A tool that
    // covers at least one of them is fine - mixed-material quotes are normal.
    const selectedTool = tools.find((t) => t.id === Number(item.tool_id));
    const toolMismatch =
        item.type === 'tooling' &&
        selectedTool !== undefined &&
        quoteMaterialTypes.length > 0 &&
        !quoteMaterialTypes.some((type) =>
            selectedTool.cut_type_ids.includes(type.id),
        );

    const selectedMaterial = materials.find(
        (m) => m.id === Number(item.material_id),
    );

    const rowErrors = (
        [
            'type',
            'description',
            'quantity',
            'unit_cost',
            'markup_percent',
            'override_total',
            'material_id',
            'service_id',
            'business_partner_id',
            'material_dimension',
            'user_id',
            'equipment_id',
            'tool_id',
        ] as (keyof LineItem)[]
    )
        .map((field) => getError(field))
        .filter(Boolean) as string[];

    const selectClass =
        'block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500';

    // A collapsed item never hides an invalid one (errors force it back
    // open), and a read-only form never shows the editable version at all.
    const editing = !readOnly && (isEditing || rowErrors.length > 0);

    if (!editing) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-white px-4 py-2.5">
                <div className="flex min-w-0 flex-1 items-center gap-3">
                    <Badge variant="secondary" className="shrink-0 capitalize">
                        {item.type}
                    </Badge>
                    <span className="truncate text-sm text-gray-900">
                        {item.description || 'Untitled line item'}
                    </span>
                    {item.ai_note && (
                        <Badge
                            variant="warning"
                            className="shrink-0"
                            title={item.ai_note}
                        >
                            AI: check
                        </Badge>
                    )}
                    <span className="hidden shrink-0 text-xs text-gray-400 sm:inline">
                        {item.quantity} ×{' '}
                        {currency.format(Number(item.unit_cost) || 0)}
                    </span>
                </div>
                <div className="flex shrink-0 items-center gap-4">
                    <span className="text-sm font-medium text-gray-900">
                        {currency.format(lineTotal(item))}
                    </span>
                    {!readOnly && (
                        <>
                            <button
                                type="button"
                                onClick={() => setIsEditing(true)}
                                className="text-sm text-indigo-600 hover:underline"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                onClick={() => onRemove(index)}
                                className="text-sm text-red-600 hover:underline"
                            >
                                Remove
                            </button>
                        </>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-md border border-gray-200 bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Type" />
                        <SearchableSelect
                            wrapperClassName="mt-1"
                            className={selectClass}
                            value={item.type}
                            onChange={(value) => changeType(value as LineItem['type'])}
                            options={[
                                { value: 'material', label: 'Material' },
                                { value: 'labor', label: 'Labor' },
                                { value: 'equipment', label: 'Equipment' },
                                { value: 'tooling', label: 'Tooling' },
                            ]}
                        />
                    </div>

                    {item.type === 'material' && (
                        <div>
                            <InputLabel value="Material" />
                            <div className="mt-1">
                                <SearchableSelect
                                    className={selectClass}
                                    value={
                                        item.material_id
                                            ? String(item.material_id)
                                            : ''
                                    }
                                    onChange={selectMaterial}
                                    emptyLabel="Custom (not in catalog)"
                                    options={materials.map((material) => ({
                                        value: String(material.id),
                                        label: material.name,
                                    }))}
                                    emptyState={
                                        <>
                                            No materials yet.{' '}
                                            <Link href={route('materials.create')} className="font-medium underline">Create a material</Link> to
                                            price it on a quote.
                                        </>
                                    }
                                />
                            </div>
                        </div>
                    )}

                    {item.type === 'material' && selectedMaterial && (
                        <div>
                            <InputLabel value="Supplier price" />
                            <SearchableSelect
                                wrapperClassName="mt-1"
                                className={selectClass}
                                value={
                                    item.business_partner_id
                                        ? partnerKey(
                                              Number(item.business_partner_id),
                                              item.material_dimension,
                                          )
                                        : ''
                                }
                                onChange={selectMaterialPartner}
                                emptyLabel="Custom price"
                                options={selectedMaterial.partners.map((partner) => ({
                                    value: partnerKey(
                                        partner.business_partner_id,
                                        partner.dimension,
                                    ),
                                    label: `${partner.name}${partner.dimension ? ` - ${partner.dimension}` : ''} (${currency.format(partner.price)})`,
                                }))}
                                emptyState={
                                    <>
                                        No supplier prices for this material yet.{' '}
                                        <Link href={route('business-partners.create')} className="font-medium underline">Add a business partner</Link> that supplies it.
                                    </>
                                }
                            />
                        </div>
                    )}

                    {item.type === 'labor' && (
                        <div>
                            <InputLabel value="Labor source" />
                            <SearchableSelect
                                wrapperClassName="mt-1"
                                className={selectClass}
                                value={laborSourceValue()}
                                onChange={selectLaborSource}
                                emptyLabel="Custom"
                                options={[
                                    ...employees.map((employee) => ({
                                        value: `emp:${employee.user_id}`,
                                        label: `${employee.name}${
                                            employee.hourly_rate !== null
                                                ? ` (${currency.format(employee.hourly_rate)}/hr)`
                                                : ' (no rate set)'
                                        }`,
                                        group: 'Employees',
                                    })),
                                    ...services.flatMap((service) =>
                                        service.partners.map((partner) => ({
                                            value: `svc:${service.id}:${partner.business_partner_id}`,
                                            label: `${service.name} — ${partner.name} (${currency.format(partner.price)})`,
                                            group: 'Outsourced services',
                                        })),
                                    ),
                                ]}
                                emptyState={
                                    <>
                                        No team members or outsourced services yet.{' '}
                                        <Link href={route('team.index')} className="font-medium underline">Add a team member</Link> or{' '}
                                        <Link href={route('services.create')} className="font-medium underline">an outsourced service</Link>.
                                    </>
                                }
                            />
                        </div>
                    )}

                    {item.type === 'equipment' && (
                        <div>
                            <InputLabel value="Equipment" />
                            <SearchableSelect
                                wrapperClassName="mt-1"
                                className={selectClass}
                                value={item.equipment_id ? String(item.equipment_id) : ''}
                                onChange={selectEquipment}
                                emptyLabel="Custom (not in catalog)"
                                options={equipmentList.map((equipment) => ({
                                    value: String(equipment.id),
                                    label: `${equipment.name} (${currency.format(equipment.rate)}${
                                        equipment.billing_method === 'hourly' ? '/hr' : ' flat'
                                    })`,
                                }))}
                                emptyState={
                                    <>
                                        No equipment yet. <Link href={route('equipment.create')} className="font-medium underline">Create equipment</Link> to bill
                                        it on a quote.
                                    </>
                                }
                            />
                        </div>
                    )}

                    {item.type === 'tooling' && (
                        <div>
                            <InputLabel value="Tool" />
                            <SearchableSelect
                                wrapperClassName="mt-1"
                                className={selectClass}
                                value={item.tool_id ? String(item.tool_id) : ''}
                                onChange={selectTool}
                                emptyLabel="Custom (not in catalog)"
                                options={tools.map((tool) => ({
                                    value: String(tool.id),
                                    label: `${tool.name} — ${tool.cuts} (${currency.format(tool.cost)}/${tool.unit})`,
                                }))}
                                emptyState={
                                    <>
                                        No tooling yet. <Link href={route('tools.create')} className="font-medium underline">Create a tool</Link> to bill it on a
                                        quote.
                                    </>
                                }
                            />
                        </div>
                    )}
                </div>

                <div className="mt-6 flex shrink-0 items-center gap-3">
                    <button
                        type="button"
                        onClick={() => setIsEditing(false)}
                        className="text-sm text-gray-500 hover:text-foreground"
                    >
                        Done
                    </button>
                    <button
                        type="button"
                        onClick={() => onRemove(index)}
                        className="text-sm text-red-600 hover:underline"
                    >
                        Remove
                    </button>
                </div>
            </div>

            {toolMismatch && selectedTool && (
                <Alert
                    role="alert"
                    variant="warning" className="mt-3 px-2 py-1 text-xs"
                >
                    Rated for {selectedTool.cuts} - this quote's materials are{' '}
                    {quoteMaterialTypes.map((type) => type.name).join(', ')}.
                </Alert>
            )}

            <div className="mt-3">
                <InputLabel value="Description" />
                <TextInput
                    className={`mt-1 block w-full ${errorClass('description')}`}
                    value={item.description}
                    onChange={(e) =>
                        onChange(index, { description: e.target.value })
                    }
                    required
                />
            </div>

            <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <InputLabel value="Qty" />
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0.01"
                        className={`mt-1 block w-full ${errorClass('quantity')}`}
                        value={item.quantity}
                        onChange={(e) =>
                            onChange(index, { quantity: e.target.value })
                        }
                        required
                    />
                </div>
                <div>
                    <InputLabel value="Unit cost" />
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0"
                        className={`mt-1 block w-full ${errorClass('unit_cost')}`}
                        value={item.unit_cost}
                        onChange={(e) =>
                            onChange(index, { unit_cost: e.target.value })
                        }
                        required
                    />
                </div>
                <div>
                    <InputLabel value="Markup %" />
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0"
                        className={`mt-1 block w-full ${errorClass('markup_percent')}`}
                        value={item.markup_percent}
                        onChange={(e) =>
                            onChange(index, { markup_percent: e.target.value })
                        }
                        required
                    />
                </div>
                <div>
                    <InputLabel value="Override total" />
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0"
                        className={`mt-1 block w-full ${errorClass('override_total')}`}
                        placeholder="auto"
                        value={item.override_total ?? ''}
                        onChange={(e) =>
                            onChange(index, { override_total: e.target.value })
                        }
                    />
                </div>
            </div>

            <div className="mt-3 flex justify-between border-t border-gray-100 pt-2 text-sm">
                <span className="text-gray-500">Line total</span>
                <span className="font-medium text-gray-900">
                    {currency.format(lineTotal(item))}
                </span>
            </div>

            {rowErrors.length > 0 && (
                <Alert variant="danger" className="mt-2 px-2 py-1 text-sm">
                    {rowErrors.join(' ')}
                </Alert>
            )}
        </div>
    );
}
