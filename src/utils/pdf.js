import jsPDF from 'jspdf';
import { formatCurrency } from './calculations';

/**
 * Generate a PDF for a pool estimate.
 * @param {object} estimate  - the saved estimate object (includes formData + result)
 * @param {string} clientName - optional client name
 */
export function generatePDF(estimate, clientName) {
  const doc = new jsPDF({ unit: 'pt', format: 'letter' });
  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 50;
  const contentWidth = pageWidth - margin * 2;
  let y = margin;

  // --- Header ---
  doc.setFillColor(14, 116, 144); // cyan-700
  doc.rect(0, 0, pageWidth, 80, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(22);
  doc.setFont('helvetica', 'bold');
  doc.text('Pool Construction Estimate', margin, 40);
  doc.setFontSize(11);
  doc.setFont('helvetica', 'normal');
  doc.text('Professional Pool Cost Estimator', margin, 60);
  y = 100;

  doc.setTextColor(30, 30, 30);

  // --- Estimate meta ---
  doc.setFontSize(10);
  doc.setFont('helvetica', 'normal');
  const date = new Date(estimate.createdAt || Date.now()).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
  doc.text(`Date: ${date}`, margin, y);
  doc.text(`Estimate #: ${estimate.id ? estimate.id.slice(0, 8).toUpperCase() : 'N/A'}`, pageWidth - margin, y, { align: 'right' });
  y += 18;

  if (clientName) {
    doc.setFont('helvetica', 'bold');
    doc.text(`Client: ${clientName}`, margin, y);
    doc.setFont('helvetica', 'normal');
    y += 18;
  }

  if (estimate.projectName) {
    doc.setFont('helvetica', 'bold');
    doc.text(`Project: ${estimate.projectName}`, margin, y);
    doc.setFont('helvetica', 'normal');
    y += 18;
  }

  y += 10;

  // --- Pool Specs ---
  doc.setFillColor(240, 249, 255); // light blue
  doc.rect(margin, y, contentWidth, 20, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.text('Pool Specifications', margin + 8, y + 14);
  y += 28;

  const fd = estimate.formData || {};
  const specs = [
    ['Dimensions', `${fd.length || 0} ft × ${fd.width || 0} ft × ${fd.depth || 0} ft deep`],
    ['Shape', fd.shape ? fd.shape.charAt(0).toUpperCase() + fd.shape.slice(1) : 'Rectangular'],
    ['Material', estimate.result?.material || fd.material || 'Concrete'],
  ];
  doc.setFontSize(10);
  doc.setFont('helvetica', 'normal');
  specs.forEach(([label, value]) => {
    doc.text(label + ':', margin, y);
    doc.text(value, margin + 120, y);
    y += 16;
  });
  y += 10;

  // --- Cost Breakdown ---
  doc.setFillColor(240, 249, 255);
  doc.rect(margin, y, contentWidth, 20, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.text('Cost Breakdown', margin + 8, y + 14);
  y += 28;

  const result = estimate.result || {};
  const lineItems = result.lineItems || [];

  doc.setFontSize(10);
  lineItems.forEach((item, i) => {
    if (i % 2 === 0) {
      doc.setFillColor(250, 250, 250);
      doc.rect(margin, y - 12, contentWidth, 16, 'F');
    }
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(30, 30, 30);
    doc.text(item.label, margin + 4, y);
    doc.text(formatCurrency(item.amount), pageWidth - margin - 4, y, { align: 'right' });
    y += 18;

    if (y > 700) {
      doc.addPage();
      y = margin;
    }
  });

  y += 6;
  // Divider
  doc.setDrawColor(180, 180, 180);
  doc.line(margin, y, pageWidth - margin, y);
  y += 14;

  // Subtotal
  doc.setFont('helvetica', 'normal');
  doc.text('Subtotal', margin + 4, y);
  doc.text(formatCurrency(result.subtotal || 0), pageWidth - margin - 4, y, { align: 'right' });
  y += 16;

  // Contingency
  doc.text('Contingency (5%)', margin + 4, y);
  doc.text(formatCurrency(result.contingency || 0), pageWidth - margin - 4, y, { align: 'right' });
  y += 10;

  // Total line
  doc.setDrawColor(14, 116, 144);
  doc.setLineWidth(1.5);
  doc.line(margin, y, pageWidth - margin, y);
  y += 14;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.setTextColor(14, 116, 144);
  doc.text('TOTAL ESTIMATE', margin + 4, y);
  doc.text(formatCurrency(result.total || 0), pageWidth - margin - 4, y, { align: 'right' });
  y += 24;

  // --- Notes ---
  if (estimate.notes) {
    doc.setTextColor(30, 30, 30);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text('Notes:', margin, y);
    y += 14;
    doc.setFont('helvetica', 'normal');
    const lines = doc.splitTextToSize(estimate.notes, contentWidth);
    doc.text(lines, margin, y);
    y += lines.length * 14 + 10;
  }

  // --- Footer ---
  doc.setFontSize(8);
  doc.setTextColor(120, 120, 120);
  doc.setFont('helvetica', 'italic');
  const footerY = doc.internal.pageSize.getHeight() - 30;
  doc.text(
    'This estimate is valid for 30 days. Prices may vary based on site conditions and final design.',
    pageWidth / 2,
    footerY,
    { align: 'center' }
  );

  doc.save(`Pool-Estimate-${estimate.id ? estimate.id.slice(0, 8).toUpperCase() : 'NEW'}.pdf`);
}
