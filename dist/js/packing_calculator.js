/**
 * packing_calculator.js
 */

const BOX_TYPES = {
    PEQUENA: { name: 'PEQUENA', width: 29, length: 27, height: 13, weight: 1, cost: 1, volume: 29 * 27 * 13 },
    MEDIANA: { name: 'MEDIANA', width: 29, length: 54, height: 13, weight: 2.5, cost: 2, volume: 29 * 54 * 13 },
    GRANDE:  { name: 'GRANDE',  width: 29, length: 54, height: 26, weight: 3, cost: 3, volume: 29 * 54 * 26 },
};

const TRAY_TYPES = {
    50:  { cells: 50,  width: 28, length: 54, height: 5, footprint: 28 * 54 },
    72:  { cells: 72,  width: 28, length: 54, height: 5, footprint: 28 * 54 },
    105: { cells: 105, width: 28, length: 54, height: 5, footprint: 28 * 54 },
    128: { cells: 128, width: 28, length: 54, height: 4.9, footprint: 28 * 54 },
    162: { cells: 162, width: 28, length: 54, height: 4.4, footprint: 28 * 54 },
    200: { cells: 200, width: 28, length: 54, height: 4.5, footprint: 28 * 54 },
};

const FINISHED_PLANT_FOOTPRINT = { width: 10, length: 10 };

function calculatePackingAndPricing(cartItems, productDetails) {
    if (!cartItems || cartItems.length === 0) {
        return { lineItems: [], grandTotal: 0 };
    }

    const lineItems = [];
    const itemsToPack = [];
    
    // --- 1. Generate Priced Line Items & Physical Items to Pack ---
    cartItems.forEach((item, index) => {
        const details = productDetails[item.id_variedad];
        if (!details) return;

        const isEsqueje = details.tipo.toUpperCase() === 'ESQUEJE';
        item.originalIndex = index; // Keep track of original index for deletion

        if (isEsqueje) {
            let remainingQty = item.cantidad;
            const wholesalePrice = parseFloat(item.precio) || 0;
            const detailPrice = parseFloat(item.precio_detalle) || wholesalePrice;
            const traySizes = Object.keys(TRAY_TYPES).map(Number).sort((a, b) => b - a);
            
            let combinedQty = 0;
            let combinedPrice = 0;
            
            // Full Trays (Wholesale)
            for (const size of traySizes) {
                const numTrays = Math.floor(remainingQty / size);
                if (numTrays > 0) {
                    const qtyInTrays = numTrays * size;
                    combinedQty += qtyInTrays;
                    combinedPrice += qtyInTrays * wholesalePrice;
                    for (let i = 0; i < numTrays; i++) {
                        itemsToPack.push({ name: 'TRAY', ...TRAY_TYPES[size] });
                    }
                    remainingQty %= size;
                }
            }

            // Remainder (Detail)
            if (remainingQty > 0) {
                if (wholesalePrice !== detailPrice && combinedQty > 0) {
                     // Different prices, create a new line item for the remainder
                    lineItems.push({ name: `${item.nombre} (Bandeja Completa)`, quantity: combinedQty, unitPrice: wholesalePrice, subtotal: combinedPrice, originalIndex: item.originalIndex });
                    lineItems.push({ name: `${item.nombre} (Restante)`, quantity: remainingQty, unitPrice: detailPrice, subtotal: remainingQty * detailPrice, originalIndex: item.originalIndex });
                } else {
                    // Prices are the same, or it's the only block of items
                    combinedQty += remainingQty;
                    combinedPrice += remainingQty * (detailPrice > 0 ? detailPrice : wholesalePrice);
                    lineItems.push({ name: item.nombre, quantity: combinedQty, unitPrice: combinedPrice / combinedQty, subtotal: combinedPrice, originalIndex: item.originalIndex });
                }
                const smallTraySizes = Object.keys(TRAY_TYPES).map(Number).sort((a, b) => a - b);
                let bestTraySize = smallTraySizes.find(size => size >= remainingQty) || smallTraySizes[smallTraySizes.length - 1];
                itemsToPack.push({ name: 'TRAY', ...TRAY_TYPES[bestTraySize] });
            } else if (combinedQty > 0) {
                 // Only full trays were added
                 lineItems.push({ name: item.nombre, quantity: combinedQty, unitPrice: wholesalePrice, subtotal: combinedPrice, originalIndex: item.originalIndex });
            }

        } else { // Finished Plant
            const unitPrice = (parseFloat(item.precio_detalle) > 0 ? parseFloat(item.precio_detalle) : parseFloat(item.precio)) || 0;
            lineItems.push({ name: item.nombre, quantity: item.cantidad, unitPrice: unitPrice, subtotal: item.cantidad * unitPrice, originalIndex: item.originalIndex });
            
            let plantHeight = 10;
            if (details && details.tamano) {
                const matches = details.tamano.match(/(\d+)-(\d+)/);
                plantHeight = matches ? Math.max(parseInt(matches[1], 10), parseInt(matches[2], 10)) : (parseInt(details.tamano, 10) || 10);
            }
            for (let i = 0; i < item.cantidad; i++) {
                itemsToPack.push({ name: 'PLANT', height: plantHeight, width: FINISHED_PLANT_FOOTPRINT.width, length: FINISHED_PLANT_FOOTPRINT.length });
            }
        }
    });
    
    // --- 2. Pack all physical items ---
    const packedBoxes = packItems(itemsToPack);
    const packingCost = packedBoxes.reduce((acc, box) => acc + box.cost, 0);
    const boxCount = packedBoxes.length;

    if (boxCount > 0) {
        lineItems.push({
            name: 'PACKING',
            quantity: boxCount,
            unitPrice: packingCost / boxCount,
            subtotal: packingCost,
        });
    }

    const grandTotal = lineItems.reduce((acc, item) => acc + item.subtotal, 0);

    return { lineItems, grandTotal };
}

function packItems(items) {
    // Sort items by volume, largest first, to pack biggest items first
    items.sort((a, b) => (b.height * b.width * b.length) - (a.height * a.width * a.length));
    
    let boxes = [];

    for (const item of items) {
        let placed = false;
        // Try to place in an existing box first
        for (const box of boxes) {
            if (canItemFitInBox(item, box)) {
                // Add item to the most appropriate layer or a new one
                placeItemInBox(item, box);
                placed = true;
                break;
            }
        }

        // If not placed, open a new box
        if (!placed) {
            const boxOrder = [BOX_TYPES.PEQUENA, BOX_TYPES.MEDIANA, BOX_TYPES.GRANDE];
            for (const boxTemplate of boxOrder) {
                const itemDims = [item.width, item.length, item.height].sort((a, b) => b - a);
                const boxDims = [boxTemplate.width, boxTemplate.length, boxTemplate.height].sort((a, b) => b - a);
                
                if (itemDims[0] <= boxDims[0] && itemDims[1] <= boxDims[1] && itemDims[2] <= boxDims[2]) {
                    let newBox = {
                        ...boxTemplate,
                        layers: [], // Start with no layers
                        type: boxTemplate.name
                    };
                    placeItemInBox(item, newBox);
                    boxes.push(newBox);
                    placed = true;
                    break;
                }
            }
            if (!placed) console.error("Item too large for any box:", item);
        }
    }
    return boxes;
}

function canItemFitInBox(item, box) {
    // Total height of all layers in the box
    const totalLayerHeight = box.layers.reduce((h, layer) => h + layer.height, 0);
    
    // Try to fit into an existing layer
    for(const layer of box.layers) {
        // Can only fit if heights are very similar (e.g., same tray type) and there's area
        if (Math.abs(item.height - layer.height) < 0.5 && (layer.occupiedArea + (item.width * item.length) <= layer.totalArea)) {
            return true;
        }
    }

    // Try to fit by creating a new layer
    if (totalLayerHeight + item.height <= box.height) {
        return (item.width * item.length) <= (box.width * box.length);
    }
    
    return false;
}

function placeItemInBox(item, box) {
     // Try to fit into an existing layer first
    for(const layer of box.layers) {
        if (Math.abs(item.height - layer.height) < 0.5 && (layer.occupiedArea + (item.width * item.length) <= layer.totalArea)) {
            layer.occupiedArea += item.width * item.length;
            return; // Placed in existing layer
        }
    }

    // If not placed, create a new layer for it
    box.layers.push({
        height: item.height,
        occupiedArea: item.width * item.length,
        totalArea: box.width * box.length
    });
}
