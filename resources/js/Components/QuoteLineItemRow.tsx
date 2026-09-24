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

export default function QuoteLineItemRow({
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
}) {
    const errorClass = (field: keyof LineItem) =>
        getError(field) ? 'border-red-400' : '';

    const changeType = (type: LineItem['type']) => {
        onChange(index, {
            type,
            markup_percent: markupDefaults[type],
            material_id: null,
            service_id: null,
            business_partner_id: null,
            user_id: null,
            equipment_id: null,
            tool_id: null,
        });
    };

    const selectMaterial = (materialId: string) => {
        if (materialId === '') {
            onChange(index, { material_id: null, business_partner_id: null });
            return;
        }
        const material = materials.find((m) => m.id === Number(materialId));
        onChange(index, {
            material_id: Number(materialId),
            business_partner_id: null,
            description: material?.name ?? item.description,
        });
    };

    const selectMaterialPartner = (partnerId: string) => {
        const material = materials.find((m) => m.id === Number(item.material_id));
        const partner = material?.partners.find(
            (p) => p.business_partner_id === Number(partnerId),
        );

        onChange(index, {
            business_partner_id: partner ? Number(partnerId) : null,
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
        const equipment = equipmentList.find((e) => e.id === Number(equipmentId));
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
            description: tool ? `${tool.name} (${tool.cuts})` : item.description,
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

    const selectedMaterial = materials.find((m) => m.id === Number(item.material_id));

    const rowErrors = (
        [
            'type', 'description', 'quantity', 'unit_cost', 'markup_percent',
            'override_total', 'material_id', 'service_id', 'business_partner_id',
            'user_id', 'equipment_id', 'tool_id',
        ] as (keyof LineItem)[]
    )
        .map((field) => getError(field))
        .filter(Boolean) as string[];

    return (
        <>
            <tr>
                <td className="px-3 py-2 align-top">
                    <select
                        className="block w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={item.type}
                        onChange={(e) =>
                            changeType(e.target.value as LineItem['type'])
                        }
                    >
                        <option value="material">Material</option>
                        <option value="labor">Labor</option>
                        <option value="equipment">Equipment</option>
                        <option value="tooling">Tooling</option>
                    </select>
                </td>

                <td className="px-3 py-2 align-top">
                    <div className="space-y-1">
                        {item.type === 'material' && (
                            <select
                                className="block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.material_id ?? ''}
                                onChange={(e) => selectMaterial(e.target.value)}
                            >
                                <option value="">Custom (not in catalog)</option>
                                {materials.map((material) => (
                                    <option key={material.id} value={material.id}>
                                        {material.name}
                                    </option>
                                ))}
                            </select>
                        )}

                        {item.type === 'material' && selectedMaterial && (
                            <select
                                className="block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.business_partner_id ?? ''}
                                onChange={(e) =>
                                    selectMaterialPartner(e.target.value)
                                }
                            >
                                <option value="">Custom price</option>
                                {selectedMaterial.partners.map((partner) => (
                                    <option
                                        key={partner.business_partner_id}
                                        value={partner.business_partner_id}
                                    >
                                        {partner.name} ({currency.format(partner.price)})
                                    </option>
                                ))}
                            </select>
                        )}

                        {item.type === 'labor' && (
                            <select
                                className="block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={laborSourceValue()}
                                onChange={(e) =>
                                    selectLaborSource(e.target.value)
                                }
                            >
                                <option value="">Custom</option>
                                {employees.length > 0 && (
                                    <optgroup label="Employees">
                                        {employees.map((employee) => (
                                            <option
                                                key={`emp:${employee.user_id}`}
                                                value={`emp:${employee.user_id}`}
                                            >
                                                {employee.name}
                                                {employee.hourly_rate !== null
                                                    ? ` (${currency.format(employee.hourly_rate)}/hr)`
                                                    : ' (no rate set)'}
                                            </option>
                                        ))}
                                    </optgroup>
                                )}
                                {services.some((s) => s.partners.length > 0) && (
                                    <optgroup label="Outsourced services">
                                        {services.flatMap((service) =>
                                            service.partners.map((partner) => (
                                                <option
                                                    key={`svc:${service.id}:${partner.business_partner_id}`}
                                                    value={`svc:${service.id}:${partner.business_partner_id}`}
                                                >
                                                    {service.name} —{' '}
                                                    {partner.name} (
                                                    {currency.format(partner.price)})
                                                </option>
                                            )),
                                        )}
                                    </optgroup>
                                )}
                            </select>
                        )}

                        {item.type === 'equipment' && (
                            <select
                                className="block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.equipment_id ?? ''}
                                onChange={(e) => selectEquipment(e.target.value)}
                            >
                                <option value="">Custom (not in catalog)</option>
                                {equipmentList.map((equipment) => (
                                    <option key={equipment.id} value={equipment.id}>
                                        {equipment.name} (
                                        {currency.format(equipment.rate)}
                                        {equipment.billing_method === 'hourly'
                                            ? '/hr'
                                            : ' flat'}
                                        )
                                    </option>
                                ))}
                            </select>
                        )}

                        {item.type === 'tooling' && (
                            <select
                                className="block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={item.tool_id ?? ''}
                                onChange={(e) => selectTool(e.target.value)}
                            >
                                <option value="">Custom (not in catalog)</option>
                                {tools.map((tool) => (
                                    <option key={tool.id} value={tool.id}>
                                        {tool.name} — {tool.cuts} (
                                        {currency.format(tool.cost)}/{tool.unit})
                                    </option>
                                ))}
                            </select>
                        )}

                        {toolMismatch && selectedTool && (
                            <p
                                role="alert"
                                className="w-48 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800"
                            >
                                Rated for {selectedTool.cuts} - this quote's
                                materials are{' '}
                                {quoteMaterialTypes
                                    .map((type) => type.name)
                                    .join(', ')}
                                .
                            </p>
                        )}

                        <TextInput
                            className={`block w-48 ${errorClass('description')}`}
                            placeholder="Description"
                            value={item.description}
                            onChange={(e) =>
                                onChange(index, { description: e.target.value })
                            }
                            required
                        />
                    </div>
                </td>

                <td className="px-3 py-2 align-top">
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0.01"
                        className={`block w-20 ${errorClass('quantity')}`}
                        value={item.quantity}
                        onChange={(e) =>
                            onChange(index, { quantity: e.target.value })
                        }
                        required
                    />
                </td>
                <td className="px-3 py-2 align-top">
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0"
                        className={`block w-24 ${errorClass('unit_cost')}`}
                        value={item.unit_cost}
                        onChange={(e) =>
                            onChange(index, { unit_cost: e.target.value })
                        }
                        required
                    />
                </td>
                <td className="px-3 py-2 align-top">
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0"
                        className={`block w-20 ${errorClass('markup_percent')}`}
                        value={item.markup_percent}
                        onChange={(e) =>
                            onChange(index, { markup_percent: e.target.value })
                        }
                        required
                    />
                </td>
                <td className="px-3 py-2 align-top">
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0"
                        className={`block w-24 ${errorClass('override_total')}`}
                        placeholder="auto"
                        value={item.override_total ?? ''}
                        onChange={(e) =>
                            onChange(index, { override_total: e.target.value })
                        }
                    />
                </td>
                <td className="whitespace-nowrap px-3 py-2 text-right align-top text-sm text-gray-900">
                    {currency.format(lineTotal(item))}
                </td>
                <td className="px-3 py-2 align-top">
                    <button
                        type="button"
                        onClick={() => onRemove(index)}
                        className="text-sm text-red-600 hover:text-red-900"
                    >
                        Remove
                    </button>
                </td>
            </tr>
            {rowErrors.length > 0 && (
                <tr>
                    <td colSpan={8} className="bg-red-50 px-3 py-1 text-sm text-red-600">
                        {rowErrors.join(' ')}
                    </td>
                </tr>
            )}
        </>
    );
}
