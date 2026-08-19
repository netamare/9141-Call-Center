import {Schema,model,models} from "mongoose";
const ReportSchema=new Schema({name:{type:String,required:true},period:{type:String,enum:["daily","weekly","monthly","yearly"],required:true},filters:Schema.Types.Mixed,generatedBy:{type:Schema.Types.ObjectId,ref:"User"},snapshot:Schema.Types.Mixed},{timestamps:true});
export default models.Report||model("Report",ReportSchema);
