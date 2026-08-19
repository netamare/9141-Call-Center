import { Schema, model, models } from "mongoose";
const FollowUpSchema=new Schema({event:{type:Schema.Types.ObjectId,ref:"Event",required:true},user:{type:Schema.Types.ObjectId,ref:"User"},note:{type:String,required:true},nextFollowUp:Date},{timestamps:true});
export default models.FollowUp||model("FollowUp",FollowUpSchema);
